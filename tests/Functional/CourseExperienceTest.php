<?php

namespace App\Tests\Functional;

use App\Entity\BankAccount;
use App\Entity\BankCategory;
use App\Entity\CourseCategory;
use App\Entity\CourseProduct;
use App\Entity\CourseReceipt;
use App\Entity\CourseReceiptLine;
use App\Entity\CourseStore;
use App\Entity\CourseStoreAlias;
use App\Entity\User;
use App\Repository\BankTransactionRepository;
use App\Repository\BankTransactionSplitRepository;
use App\Repository\CourseProductAliasRepository;
use App\Repository\CourseReceiptRepository;
use App\Service\BankEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Verifie le chemin complet du module Courses sans dependre du binaire Tesseract : un
 * App\Entity\CourseReceipt "pending_review" est seede directement (comme le ferait
 * App\Service\Course\CourseReceiptImportService apres extraction), puis on passe par l'ecran de
 * relecture/confirmation HTTP pour verifier que la App\Entity\BankTransaction agregee (et sa
 * ventilation eventuelle) est correctement creee dans Bank Manager.
 */
final class CourseExperienceTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private BankEncryptionService $encryption;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->encryption = static::getContainer()->get(BankEncryptionService::class);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testConfirmingASingleCategoryReceiptCreatesTheAggregatedBankTransaction(): void
    {
        [$user, $account, $foodCategory] = $this->baseFixture('single-category');
        $courseCategory = $this->courseCategory($user, $foodCategory, 'Epicerie salee');
        $store = $this->store($user, 'Carrefour Melun');
        $product = $this->product($user, 'Pain de mie', $courseCategory);
        $receipt = $this->pendingReceipt($user, [
            ['label' => 'Pain de mie', 'quantity' => 1, 'unit' => CourseProduct::UNIT_PIECE, 'unitPrice' => null, 'total' => 2.35, 'product' => $product, 'category' => $courseCategory],
        ]);

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/courses/receipts/' . $receipt->getPublicId() . '/review');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/courses/receipts/' . $receipt->getPublicId() . '/review', [
            '_token' => $token,
            'account' => $account->getPublicId(),
            'purchaseDate' => '2026-08-01',
            'store' => $store->getPublicId(),
            'newStoreName' => '',
            'lines' => [
                0 => [
                    'rawLabel' => 'Pain de mie',
                    'product' => $product->getPublicId(),
                    'newProductName' => '',
                    'category' => $courseCategory->getPublicId(),
                    'quantity' => '1',
                    'unit' => 'piece',
                    'unitPrice' => '',
                    'lineTotal' => '2.35',
                ],
            ],
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        /** @var CourseReceiptRepository $receipts */
        $receipts = static::getContainer()->get(CourseReceiptRepository::class);
        $confirmed = $receipts->findOneForUserByPublicId($user, $receipt->getPublicId());
        self::assertNotNull($confirmed);
        self::assertTrue($confirmed->isConfirmed());
        self::assertNotNull($confirmed->getBankTransaction());

        $transaction = $confirmed->getBankTransaction();
        self::assertSame(-2.35, $transaction->getAmount());
        self::assertSame($foodCategory->getPublicId(), $transaction->getCategory()?->getPublicId());
        self::assertSame($account->getPublicId(), $transaction->getAccount()?->getPublicId());

        /** @var BankTransactionRepository $transactions */
        $transactions = static::getContainer()->get(BankTransactionRepository::class);
        self::assertCount(1, $transactions->findRecentForUser($user, 10));
    }

    public function testConfirmingLinesAcrossTwoParentCategoriesSplitsTheBankTransaction(): void
    {
        [$user, $account, $foodCategory] = $this->baseFixture('multi-category');
        $hygieneParent = (new BankCategory())->setUser($user)->setName('Hygiene')->setType(BankCategory::TYPE_EXPENSE);
        $this->entityManager->persist($hygieneParent);
        $foodCourseCategory = $this->courseCategory($user, $foodCategory, 'Epicerie salee');
        $hygieneCourseCategory = $this->courseCategory($user, $hygieneParent, 'Hygiene & Beaute');
        $store = $this->store($user, 'Leclerc Drive');
        $bread = $this->product($user, 'Pain de mie', $foodCourseCategory);
        $soap = $this->product($user, 'Savon', $hygieneCourseCategory);
        $receipt = $this->pendingReceipt($user, [
            ['label' => 'Pain de mie', 'quantity' => 1, 'unit' => CourseProduct::UNIT_PIECE, 'unitPrice' => null, 'total' => 2.00, 'product' => $bread, 'category' => $foodCourseCategory],
            ['label' => 'Savon', 'quantity' => 1, 'unit' => CourseProduct::UNIT_PIECE, 'unitPrice' => null, 'total' => 3.00, 'product' => $soap, 'category' => $hygieneCourseCategory],
        ]);

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/courses/receipts/' . $receipt->getPublicId() . '/review');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/courses/receipts/' . $receipt->getPublicId() . '/review', [
            '_token' => $token,
            'account' => $account->getPublicId(),
            'purchaseDate' => '2026-08-01',
            'store' => $store->getPublicId(),
            'newStoreName' => '',
            'lines' => [
                0 => ['rawLabel' => 'Pain de mie', 'product' => $bread->getPublicId(), 'newProductName' => '', 'category' => $foodCourseCategory->getPublicId(), 'quantity' => '1', 'unit' => 'piece', 'unitPrice' => '', 'lineTotal' => '2.00'],
                1 => ['rawLabel' => 'Savon', 'product' => $soap->getPublicId(), 'newProductName' => '', 'category' => $hygieneCourseCategory->getPublicId(), 'quantity' => '1', 'unit' => 'piece', 'unitPrice' => '', 'lineTotal' => '3.00'],
            ],
        ]);

        self::assertResponseRedirects();

        /** @var CourseReceiptRepository $receipts */
        $receipts = static::getContainer()->get(CourseReceiptRepository::class);
        $confirmed = $receipts->findOneForUserByPublicId($user, $receipt->getPublicId());
        $transaction = $confirmed->getBankTransaction();
        self::assertNotNull($transaction);
        self::assertSame(-5.0, $transaction->getAmount());

        /** @var BankTransactionSplitRepository $splitRepository */
        $splitRepository = static::getContainer()->get(BankTransactionSplitRepository::class);
        $splits = $splitRepository->findForTransaction($transaction);
        self::assertCount(2, $splits);

        // La somme des lignes doit etre strictement egale au montant de la transaction (regle deja
        // imposee par BankTransactionSplitManager::replace(), on la reverifie ici bout en bout).
        self::assertSame(-5.0, array_sum(array_map(static fn ($s) => $s->getAmount(), $splits)));

        $byCategory = [];
        foreach ($splits as $split) {
            $byCategory[$split->getCategory()->getPublicId()] = $split->getAmount();
        }
        self::assertSame(-2.0, $byCategory[$foodCategory->getPublicId()] ?? null);
        self::assertSame(-3.0, $byCategory[$hygieneParent->getPublicId()] ?? null);
    }

    /**
     * Meme verification qu'au-dessus mais avec 3 categories meres distinctes (comme sur un vrai ticket de
     * supermarche melangeant alimentaire, hygiene et animalerie), pour s'assurer que la ventilation ne
     * suppose pas implicitement un maximum de 2 groupes.
     */
    public function testConfirmingLinesAcrossThreeParentCategoriesSplitsTheBankTransactionCorrectly(): void
    {
        [$user, $account, $foodCategory] = $this->baseFixture('three-category');
        $hygieneParent = (new BankCategory())->setUser($user)->setName('Hygiene')->setType(BankCategory::TYPE_EXPENSE);
        $petParent = (new BankCategory())->setUser($user)->setName('Animaux')->setType(BankCategory::TYPE_EXPENSE);
        $this->entityManager->persist($hygieneParent);
        $this->entityManager->persist($petParent);
        $foodCourseCategory = $this->courseCategory($user, $foodCategory, 'Epicerie salee');
        $hygieneCourseCategory = $this->courseCategory($user, $hygieneParent, 'Hygiene & Beaute');
        $petCourseCategory = $this->courseCategory($user, $petParent, 'Animalerie');
        $store = $this->store($user, 'Super U Gundershoffen');
        $bread = $this->product($user, 'Pain de mie', $foodCourseCategory);
        $soap = $this->product($user, 'Savon', $hygieneCourseCategory);
        $catFood = $this->product($user, 'Croquettes chat', $petCourseCategory);
        $receipt = $this->pendingReceipt($user, [
            ['label' => 'Pain de mie', 'quantity' => 1, 'unit' => CourseProduct::UNIT_PIECE, 'unitPrice' => null, 'total' => 2.00, 'product' => $bread, 'category' => $foodCourseCategory],
            ['label' => 'Savon', 'quantity' => 1, 'unit' => CourseProduct::UNIT_PIECE, 'unitPrice' => null, 'total' => 3.00, 'product' => $soap, 'category' => $hygieneCourseCategory],
            ['label' => 'Croquettes chat', 'quantity' => 1, 'unit' => CourseProduct::UNIT_PIECE, 'unitPrice' => null, 'total' => 8.90, 'product' => $catFood, 'category' => $petCourseCategory],
        ]);

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/courses/receipts/' . $receipt->getPublicId() . '/review');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/courses/receipts/' . $receipt->getPublicId() . '/review', [
            '_token' => $token,
            'account' => $account->getPublicId(),
            'purchaseDate' => '2026-08-01',
            'store' => $store->getPublicId(),
            'newStoreName' => '',
            'lines' => [
                0 => ['rawLabel' => 'Pain de mie', 'product' => $bread->getPublicId(), 'newProductName' => '', 'category' => $foodCourseCategory->getPublicId(), 'quantity' => '1', 'unit' => 'piece', 'unitPrice' => '', 'lineTotal' => '2.00'],
                1 => ['rawLabel' => 'Savon', 'product' => $soap->getPublicId(), 'newProductName' => '', 'category' => $hygieneCourseCategory->getPublicId(), 'quantity' => '1', 'unit' => 'piece', 'unitPrice' => '', 'lineTotal' => '3.00'],
                2 => ['rawLabel' => 'Croquettes chat', 'product' => $catFood->getPublicId(), 'newProductName' => '', 'category' => $petCourseCategory->getPublicId(), 'quantity' => '1', 'unit' => 'piece', 'unitPrice' => '', 'lineTotal' => '8.90'],
            ],
        ]);

        self::assertResponseRedirects();

        /** @var CourseReceiptRepository $receipts */
        $receipts = static::getContainer()->get(CourseReceiptRepository::class);
        $confirmed = $receipts->findOneForUserByPublicId($user, $receipt->getPublicId());
        $transaction = $confirmed->getBankTransaction();
        self::assertNotNull($transaction);
        self::assertSame(-13.90, $transaction->getAmount());

        /** @var BankTransactionSplitRepository $splitRepository */
        $splitRepository = static::getContainer()->get(BankTransactionSplitRepository::class);
        $splits = $splitRepository->findForTransaction($transaction);
        self::assertCount(3, $splits);
        self::assertEqualsWithDelta(-13.90, array_sum(array_map(static fn ($s) => $s->getAmount(), $splits)), 0.001);

        $byCategory = [];
        foreach ($splits as $split) {
            $byCategory[$split->getCategory()->getPublicId()] = $split->getAmount();
        }
        self::assertSame(-2.0, $byCategory[$foodCategory->getPublicId()] ?? null);
        self::assertSame(-3.0, $byCategory[$hygieneParent->getPublicId()] ?? null);
        self::assertSame(-8.9, $byCategory[$petParent->getPublicId()] ?? null);
    }

    /**
     * Sur un ticket DEJA confirme, on peut corriger le produit associe a une ligne (le libelle du ticket
     * a ete associe au mauvais produit). L'alias magasin+libelle -> produit doit etre mis a jour, mais la
     * App\Entity\BankTransaction et sa ventilation deja creees ne doivent surtout pas etre modifiees
     * (memes montants, meme categorie) puisque seul le rattachement produit change.
     */
    public function testReassigningAProductOnAConfirmedLineUpdatesTheAliasWithoutTouchingTheBankTransaction(): void
    {
        [$user, $account, $foodCategory] = $this->baseFixture('reassign-product');
        $courseCategory = $this->courseCategory($user, $foodCategory, 'Epicerie salee');
        $store = $this->store($user, 'Carrefour Melun');
        $wrongProduct = $this->product($user, 'Gateau chocolat', $courseCategory);
        $correctProduct = $this->product($user, 'Gelatine Vahine', $courseCategory);
        $receipt = $this->pendingReceipt($user, [
            ['label' => 'Gelatine Vahine ST 9 feuilles', 'quantity' => 1, 'unit' => CourseProduct::UNIT_PIECE, 'unitPrice' => null, 'total' => 1.36, 'product' => $wrongProduct, 'category' => $courseCategory],
        ]);

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/courses/receipts/' . $receipt->getPublicId() . '/review');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/courses/receipts/' . $receipt->getPublicId() . '/review', [
            '_token' => $token,
            'account' => $account->getPublicId(),
            'purchaseDate' => '2026-08-01',
            'store' => $store->getPublicId(),
            'newStoreName' => '',
            'lines' => [
                0 => ['rawLabel' => 'Gelatine Vahine ST 9 feuilles', 'product' => $wrongProduct->getPublicId(), 'newProductName' => '', 'category' => $courseCategory->getPublicId(), 'quantity' => '1', 'unit' => 'piece', 'unitPrice' => '', 'lineTotal' => '1.36'],
            ],
        ]);
        self::assertResponseRedirects();

        /** @var CourseReceiptRepository $receipts */
        $receipts = static::getContainer()->get(CourseReceiptRepository::class);
        $confirmed = $receipts->findOneForUserByPublicId($user, $receipt->getPublicId());
        self::assertTrue($confirmed->isConfirmed());
        $transactionBefore = $confirmed->getBankTransaction();
        self::assertNotNull($transactionBefore);
        $originalAmount = $transactionBefore->getAmount();
        $originalCategoryId = $transactionBefore->getCategory()?->getPublicId();

        $line = $confirmed->getLines()->first();
        self::assertNotFalse($line);
        $reassignCrawler = $this->client->request('GET', '/courses/receipts/' . $confirmed->getPublicId());
        self::assertResponseIsSuccessful();
        $reassignToken = (string) $reassignCrawler->filter(sprintf('form[action="%s"] input[name="_token"]', '/courses/receipts/' . $confirmed->getPublicId() . '/lines/' . $line->getPublicId() . '/product'))->attr('value');

        $this->client->request('POST', '/courses/receipts/' . $confirmed->getPublicId() . '/lines/' . $line->getPublicId() . '/product', [
            '_token' => $reassignToken,
            'product' => $correctProduct->getPublicId(),
        ]);
        self::assertResponseRedirects();

        $this->entityManager->clear();
        $reloaded = $receipts->findOneForUserByPublicId($user, $receipt->getPublicId());
        $reloadedLine = $reloaded->getLines()->first();
        self::assertSame($correctProduct->getPublicId(), $reloadedLine->getProduct()->getPublicId());

        // La transaction Bank et sa categorie/montant restent strictement inchanges.
        $transactionAfter = $reloaded->getBankTransaction();
        self::assertSame($originalAmount, $transactionAfter->getAmount());
        self::assertSame($originalCategoryId, $transactionAfter->getCategory()?->getPublicId());

        /** @var CourseProductAliasRepository $aliasRepository */
        $aliasRepository = static::getContainer()->get(CourseProductAliasRepository::class);
        $alias = $aliasRepository->findOneForUserStoreAndLabel($user, $store, CourseStoreAlias::normalize('Gelatine Vahine ST 9 feuilles'));
        self::assertNotNull($alias);
        self::assertSame($correctProduct->getPublicId(), $alias->getProduct()->getPublicId());
    }

    /** @return array{User, BankAccount, BankCategory} */
    private function baseFixture(string $username): array
    {
        $user = (new User())->setUsername($username)->setPassword('test-password')->setEmail($username . '@example.test')->setEmailVerified(true);
        $account = (new BankAccount())->setUser($user)->setEncryptedName($this->encryptionOrPlaceholder('Compte courant'))->setType(BankAccount::TYPE_CURRENT);
        $foodCategory = (new BankCategory())->setUser($user)->setName('Alimentation')->setType(BankCategory::TYPE_EXPENSE);
        $this->entityManager->persist($user);
        $this->entityManager->persist($account);
        $this->entityManager->persist($foodCategory);
        $this->entityManager->flush();

        return [$user, $account, $foodCategory];
    }

    private function courseCategory(User $user, BankCategory $parent, string $name): CourseCategory
    {
        $category = (new CourseCategory())->setUser($user)->setName($name)->setParentCategory($parent)->setColor('#ea580c')->setIcon('tag');
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    private function store(User $user, string $name): CourseStore
    {
        $store = (new CourseStore())->setUser($user)->setEncryptedName($this->encryption->encrypt($name) ?? '');
        $this->entityManager->persist($store);
        $this->entityManager->flush();

        return $store;
    }

    private function product(User $user, string $name, CourseCategory $category): CourseProduct
    {
        $product = (new CourseProduct())->setUser($user)->setEncryptedName($this->encryption->encrypt($name) ?? '')->setCategory($category)->setDefaultUnit(CourseProduct::UNIT_PIECE);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    /** @param array<int,array{label:string,quantity:float,unit:string,unitPrice:?float,total:float,product:CourseProduct,category:CourseCategory}> $lines */
    private function pendingReceipt(User $user, array $lines): CourseReceipt
    {
        $receipt = (new CourseReceipt())
            ->setUser($user)
            ->setSourceFormat(CourseReceipt::SOURCE_PHOTO)
            ->setStatus(CourseReceipt::STATUS_PENDING_REVIEW)
            ->setPurchaseDate(new \DateTime('2026-08-01'))
            ->setTotalAmount(array_sum(array_column($lines, 'total')));

        foreach ($lines as $data) {
            $line = (new CourseReceiptLine())
                ->setUser($user)
                ->setEncryptedRawLabel($this->encryption->encrypt($data['label']) ?? '')
                ->setProduct($data['product'])
                ->setCategory($data['category'])
                ->setQuantity($data['quantity'])
                ->setUnit($data['unit'])
                ->setUnitPrice($data['unitPrice'])
                ->setLineTotal($data['total'])
                ->setConfidence(CourseReceiptLine::CONFIDENCE_HIGH);
            $receipt->addLine($line);
        }

        $this->entityManager->persist($receipt);
        $this->entityManager->flush();

        return $receipt;
    }

    private function encryptionOrPlaceholder(string $value): string
    {
        return $this->encryption->encrypt($value) ?? '';
    }
}
