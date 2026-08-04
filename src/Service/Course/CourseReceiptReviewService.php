<?php

namespace App\Service\Course;

use App\Entity\BankAccount;
use App\Entity\CourseCategory;
use App\Entity\CourseProduct;
use App\Entity\CourseReceipt;
use App\Entity\CourseReceiptLine;
use App\Entity\CourseStore;
use App\Entity\User;
use App\Repository\BankCategoryRepository;
use App\Repository\CourseCategoryRepository;
use App\Repository\CourseProductRepository;
use App\Repository\CourseReceiptLineRepository;
use App\Repository\CourseStoreRepository;
use App\Service\BankEncryptionService;
use App\Service\BankTransactionSplitManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Valide et confirme l'ecran de relecture systematique d'un ticket : chaque ligne editee doit avoir un
 * produit et une categorie Courses valides, la somme des lignes devient le montant du ticket. Cree la
 * App\Entity\BankTransaction agregee via App\Service\Course\CourseBankBridge puis ventile par categorie
 * parente Bank en reutilisant directement App\Service\BankTransactionSplitManager::replace() (aucune
 * reecriture de la logique de ventilation existante).
 */
final class CourseReceiptReviewService
{
    public function __construct(
        private readonly CourseReceiptLineRepository $lineRepository,
        private readonly CourseCategoryRepository $categoryRepository,
        private readonly CourseProductRepository $productRepository,
        private readonly CourseStoreRepository $storeRepository,
        private readonly BankCategoryRepository $bankCategoryRepository,
        private readonly CourseProductMatcher $productMatcher,
        private readonly CourseStoreMatcher $storeMatcher,
        private readonly CourseBankBridge $bankBridge,
        private readonly BankTransactionSplitManager $splitManager,
        private readonly BankEncryptionService $encryption,
        private readonly CourseAuditLogger $auditLogger,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<int,array{rawLabel?:string,product?:string,newProductName?:string,category?:string,quantity?:mixed,unit?:string,unitPrice?:mixed,lineTotal?:mixed}> $rows
     */
    public function confirm(
        User $user,
        CourseReceipt $receipt,
        BankAccount $account,
        ?string $storePublicId,
        string $newStoreName,
        \DateTimeInterface $purchaseDate,
        array $rows,
    ): CourseReceipt {
        if ($receipt->isConfirmed()) {
            throw new \DomainException('Ce ticket est deja confirme.');
        }

        $store = $this->resolveStore($user, $storePublicId, $newStoreName);
        if ($store === null) {
            throw new \InvalidArgumentException('Choisissez un magasin existant ou saisissez son nom pour en creer un nouveau.');
        }

        $normalizedLines = $this->normalizeRows($user, $rows);
        if ($normalizedLines === []) {
            throw new \InvalidArgumentException('Le ticket doit contenir au moins une ligne valide (produit, categorie et montant).');
        }

        foreach ($this->lineRepository->findForReceipt($receipt) as $existing) {
            $receipt->removeLine($existing);
            $this->entityManager->remove($existing);
        }

        $sum = 0.0;
        foreach ($normalizedLines as $data) {
            $line = (new CourseReceiptLine())
                ->setUser($user)
                ->setEncryptedRawLabel($this->encryption->encrypt($data['rawLabel']) ?? '')
                ->setProduct($data['product'])
                ->setCategory($data['category'])
                ->setQuantity($data['quantity'])
                ->setUnit($data['unit'])
                ->setUnitPrice($data['unitPrice'])
                ->setLineTotal($data['lineTotal'])
                ->setConfidence(CourseReceiptLine::CONFIDENCE_HIGH);

            $receipt->addLine($line);
            $this->entityManager->persist($line);
            $this->productMatcher->learn($user, $data['rawLabel'], $data['product'], $store);
            $sum += $data['lineTotal'];
        }

        $storeGuess = $this->encryption->decrypt($receipt->getEncryptedStoreNameGuess());
        if ($storeGuess !== '') {
            $this->storeMatcher->learn($user, $storeGuess, $store);
        }

        $receipt->setStore($store);
        $receipt->setAccount($account);
        $receipt->setPurchaseDate($purchaseDate);
        $receipt->setTotalAmount(round($sum, 2));

        $defaultCategory = $this->bankCategoryRepository->findOneByName($user, 'Alimentation')
            ?? $normalizedLines[0]['category']->getParentCategory();
        $transaction = $this->bankBridge->createExpense($user, $receipt, $account, $defaultCategory, $store);

        $byParent = [];
        foreach ($normalizedLines as $data) {
            $parent = $data['category']->getParentCategory();
            $key = $parent->getPublicId();
            $byParent[$key]['category'] ??= $parent;
            $byParent[$key]['amount'] = ($byParent[$key]['amount'] ?? 0.0) + $data['lineTotal'];
        }

        if (count($byParent) >= 2) {
            $splitRows = array_map(static fn (array $entry) => [
                'label' => $entry['category']->getName(),
                'amount' => round($entry['amount'], 2),
                'category' => $entry['category']->getPublicId(),
            ], array_values($byParent));
            $this->splitManager->replace($user, $transaction, $splitRows);
        } else {
            $only = array_values($byParent)[0];
            $transaction->setCategory($only['category']);
        }

        $receipt->markConfirmed();
        $this->auditLogger->record($user, 'courses.receipt.confirm', 'course_receipt', $receipt->getPublicId(), [
            'lines' => count($normalizedLines),
            'total' => $receipt->getTotalAmount(),
            'store' => $store->getPublicId(),
        ]);

        return $receipt;
    }

    /**
     * Corrige, sur un ticket DEJA confirme, quel produit correspond a une ligne (ex: le libelle brut a
     * ete associe au mauvais produit lors de la relecture). Ne touche ni a la quantite, ni au prix, ni a
     * la categorie, ni au montant de la ligne : la App\Entity\BankTransaction agregee et sa ventilation
     * deja creees ne sont donc jamais impactees. Met a jour l'alias magasin+libelle -> produit pour que
     * les prochains tickets du meme magasin soient correctement auto-associes.
     */
    public function reassignLineProduct(User $user, CourseReceiptLine $line, CourseProduct $product): void
    {
        $receipt = $line->getReceipt();
        $store = $receipt?->getStore();
        if ($receipt === null || $store === null || !$receipt->isConfirmed()) {
            throw new \DomainException('Ce ticket ne peut pas etre corrige (magasin manquant ou ticket non confirme).');
        }

        $rawLabel = $this->encryption->decrypt($line->getEncryptedRawLabel());
        $line->setProduct($product);
        $this->productMatcher->learn($user, $rawLabel, $product, $store);

        $this->auditLogger->record($user, 'courses.receipt_line.reassign_product', 'course_receipt_line', $line->getPublicId(), [
            'receipt' => $receipt->getPublicId(),
            'product' => $product->getPublicId(),
        ]);
    }

    /**
     * @param array<int,array{rawLabel?:string,product?:string,newProductName?:string,category?:string,quantity?:mixed,unit?:string,unitPrice?:mixed,lineTotal?:mixed}> $rows
     * @return array<int,array{rawLabel:string,product:CourseProduct,category:CourseCategory,quantity:float,unit:string,unitPrice:?float,lineTotal:float}>
     */
    private function normalizeRows(User $user, array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $rawLabel = trim((string) ($row['rawLabel'] ?? ''));
            $lineTotal = $this->toFloat((string) ($row['lineTotal'] ?? '0'));
            if ($rawLabel === '' && abs($lineTotal) < 0.005) {
                continue;
            }
            if ($rawLabel === '') {
                throw new \InvalidArgumentException('Chaque ligne doit avoir un libelle.');
            }
            if ($lineTotal <= 0) {
                throw new \InvalidArgumentException(sprintf('La ligne "%s" doit avoir un montant strictement positif.', $rawLabel));
            }

            $categoryPublicId = trim((string) ($row['category'] ?? ''));
            $category = $categoryPublicId !== '' ? $this->categoryRepository->findOneForUserByPublicId($user, $categoryPublicId) : null;
            if ($category === null) {
                throw new \InvalidArgumentException(sprintf('La ligne "%s" doit avoir une categorie valide.', $rawLabel));
            }

            $product = $this->resolveProduct($user, (string) ($row['product'] ?? ''), (string) ($row['newProductName'] ?? ''), $category);
            if ($product === null) {
                throw new \InvalidArgumentException(sprintf('La ligne "%s" doit avoir un produit (existant ou nouveau).', $rawLabel));
            }

            $quantityRaw = $this->toFloat((string) ($row['quantity'] ?? '1'));
            if ($quantityRaw < 0) {
                throw new \InvalidArgumentException(sprintf('La ligne "%s" doit avoir une quantite positive.', $rawLabel));
            }
            $quantity = $quantityRaw ?: 1.0;
            $unit = (string) ($row['unit'] ?? CourseProduct::UNIT_PIECE);
            $unitPriceRaw = trim((string) ($row['unitPrice'] ?? ''));
            $unitPrice = $unitPriceRaw !== '' ? $this->toFloat($unitPriceRaw) : null;

            $normalized[] = [
                'rawLabel' => $rawLabel,
                'product' => $product,
                'category' => $category,
                'quantity' => $quantity,
                'unit' => in_array($unit, CourseProduct::UNITS, true) ? $unit : CourseProduct::UNIT_PIECE,
                'unitPrice' => $unitPrice,
                'lineTotal' => $lineTotal,
            ];
        }

        return $normalized;
    }

    private function resolveProduct(User $user, string $productPublicId, string $newProductName, CourseCategory $category): ?CourseProduct
    {
        if ($productPublicId !== '') {
            $product = $this->productRepository->findOneForUserByPublicId($user, $productPublicId);
            if ($product !== null) {
                return $product;
            }
        }

        $newProductName = trim($newProductName);
        if ($newProductName === '') {
            return null;
        }

        $product = (new CourseProduct())
            ->setUser($user)
            ->setEncryptedName($this->encryption->encrypt($newProductName) ?? '')
            ->setCategory($category);

        $this->entityManager->persist($product);

        return $product;
    }

    private function resolveStore(User $user, ?string $storePublicId, string $newStoreName): ?CourseStore
    {
        if ($storePublicId !== null && $storePublicId !== '') {
            $store = $this->storeRepository->findOneForUserByPublicId($user, $storePublicId);
            if ($store !== null) {
                return $store;
            }
        }

        $newStoreName = trim($newStoreName);
        if ($newStoreName === '') {
            return null;
        }

        $store = (new CourseStore())
            ->setUser($user)
            ->setEncryptedName($this->encryption->encrypt($newStoreName) ?? '');

        $this->entityManager->persist($store);

        return $store;
    }

    private function toFloat(string $value): float
    {
        $value = trim(str_replace(["\xc2\xa0", ' '], '', $value));
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
        }

        return (float) str_replace(',', '.', $value);
    }
}
