<?php

namespace App\Controller;

use App\Entity\BankAccount;
use App\Entity\CourseProduct;
use App\Entity\CourseReceipt;
use App\Entity\CourseShoppingList;
use App\Entity\CourseShoppingListItem;
use App\Entity\CourseStore;
use App\Form\CourseProductType;
use App\Form\CourseReceiptUploadType;
use App\Form\CourseShoppingListItemType;
use App\Form\CourseShoppingListType;
use App\Form\CourseStoreType;
use App\Repository\BankAccountRepository;
use App\Repository\CourseAttachmentRepository;
use App\Repository\CourseCategoryRepository;
use App\Repository\CourseImportProfileRepository;
use App\Repository\CourseProductRepository;
use App\Repository\CourseReceiptLineRepository;
use App\Repository\CourseReceiptRepository;
use App\Repository\CourseShoppingListRepository;
use App\Repository\CourseStoreRepository;
use App\Service\BankAccountManager;
use App\Service\BankEncryptionService;
use App\Service\Course\CourseProductManager;
use App\Service\Course\CourseReceiptImportService;
use App\Service\Course\CourseReceiptReviewService;
use App\Service\Course\CourseShoppingListOptimizer;
use App\Service\Course\CourseStatisticsService;
use App\Service\Course\CourseStoreManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/courses', name: 'app_courses_')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class CourseController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(CourseStatisticsService $statistics): Response
    {
        return $this->render('courses/index.html.twig', [
            'dashboard' => $statistics->dashboard($this->getUser()),
        ]);
    }

    #[Route('/upload', name: 'receipt_upload', methods: ['GET', 'POST'])]
    public function receiptUpload(
        Request $request,
        CourseReceiptImportService $importService,
        BankAccountRepository $accountRepository,
        BankAccountManager $accountManager,
        CourseImportProfileRepository $profileRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $form = $this->createForm(CourseReceiptUploadType::class, [], [
            'account_choices' => $this->accountChoices($accountRepository, $accountManager),
            'profile_choices' => $this->profileChoices($profileRepository),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $receipt = $importService->import($this->getUser(), $data['file'], $data['account'] ?? null, $data['importProfile'] ?? null);
            $entityManager->flush();

            if ($receipt->getStatus() === CourseReceipt::STATUS_FAILED) {
                $this->addFlash('error', "L'analyse automatique a echoue : " . implode(' ', $receipt->getProcessingErrors()) . ' Vous pouvez completer le ticket manuellement depuis la relecture.');
            } else {
                $this->addFlash('success', 'Ticket analyse. Verifiez et completez les lignes detectees.');
            }

            return $this->redirectToRoute('app_courses_receipt_review', ['publicId' => $receipt->getPublicId()]);
        }

        return $this->render('courses/upload.html.twig', ['form' => $form]);
    }

    #[Route('/receipts', name: 'receipts', methods: ['GET'])]
    public function receipts(CourseReceiptRepository $receiptRepository, CourseStoreManager $storeManager): Response
    {
        return $this->render('courses/receipts.html.twig', [
            'pending' => $receiptRepository->findPendingReviewForUser($this->getUser()),
            'confirmed' => $receiptRepository->findConfirmedForUser($this->getUser()),
            'storeManager' => $storeManager,
        ]);
    }

    #[Route('/receipts/{publicId}/review', name: 'receipt_review', methods: ['GET', 'POST'])]
    public function receiptReview(
        string $publicId,
        Request $request,
        CourseReceiptRepository $receiptRepository,
        CourseReceiptReviewService $reviewService,
        BankAccountRepository $accountRepository,
        BankAccountManager $accountManager,
        CourseStoreRepository $storeRepository,
        CourseStoreManager $storeManager,
        CourseProductRepository $productRepository,
        CourseProductManager $productManager,
        CourseCategoryRepository $categoryRepository,
        BankEncryptionService $encryption,
        EntityManagerInterface $entityManager,
    ): Response {
        $receipt = $this->receiptOr404($publicId, $receiptRepository);
        if ($receipt->isConfirmed()) {
            return $this->redirectToRoute('app_courses_receipt_show', ['publicId' => $receipt->getPublicId()]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('courses_receipt_confirm_' . $publicId, (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF invalide.');
            }

            $account = $this->accountFromRequest($request, 'account', $accountRepository);
            if ($account === null) {
                $this->addFlash('error', 'Choisissez le compte qui a paye ce ticket.');
            } else {
                try {
                    $purchaseDate = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->request->get('purchaseDate')) ?: new \DateTimeImmutable('today');
                    $reviewService->confirm(
                        $this->getUser(),
                        $receipt,
                        $account,
                        (string) $request->request->get('store'),
                        (string) $request->request->get('newStoreName'),
                        $purchaseDate,
                        (array) $request->request->all('lines'),
                    );
                    $entityManager->flush();
                    $this->addFlash('success', 'Ticket confirme et reporte dans Bank Manager.');

                    return $this->redirectToRoute('app_courses_receipt_show', ['publicId' => $receipt->getPublicId()]);
                } catch (\InvalidArgumentException|\DomainException $exception) {
                    $this->addFlash('error', $exception->getMessage());
                }
            }
        }

        if ($request->isMethod('POST')) {
            // La validation a echoue plus haut : on reaffiche exactement ce que l'utilisateur a saisi
            // (et non les donnees originales en base), pour ne jamais perdre ses corrections en cours.
            $lineRows = $this->lineRowsFromSubmission((array) $request->request->all('lines'), $productRepository, $categoryRepository);
            $selectedAccountPublicId = (string) $request->request->get('account', '');
            $selectedPurchaseDate = (string) $request->request->get('purchaseDate', '');
            $selectedStorePublicId = (string) $request->request->get('store', '');
            $selectedNewStoreName = (string) $request->request->get('newStoreName', '');
        } else {
            $lineRows = [];
            foreach ($receipt->getLines() as $line) {
                $lineRows[] = [
                    'rawLabel' => $encryption->decrypt($line->getEncryptedRawLabel()),
                    'product' => $line->getProduct(),
                    'newProductName' => '',
                    'category' => $line->getCategory(),
                    'quantity' => $line->getQuantity(),
                    'unit' => $line->getUnit(),
                    'unitPrice' => $line->getUnitPrice(),
                    'lineTotal' => $line->getLineTotal(),
                    'confidence' => $line->getConfidence(),
                ];
            }
            $selectedAccountPublicId = $receipt->getAccount()?->getPublicId() ?? '';
            $selectedPurchaseDate = $receipt->getPurchaseDate() ? $receipt->getPurchaseDate()->format('Y-m-d') : '';
            $selectedStorePublicId = $receipt->getStore()?->getPublicId() ?? '';
            $selectedNewStoreName = $receipt->getStore() === null ? $encryption->decrypt($receipt->getEncryptedStoreNameGuess()) : '';
        }

        return $this->render('courses/review.html.twig', [
            'receipt' => $receipt,
            'lineRows' => $lineRows,
            'selectedAccountPublicId' => $selectedAccountPublicId,
            'selectedPurchaseDate' => $selectedPurchaseDate,
            'selectedStorePublicId' => $selectedStorePublicId,
            'selectedNewStoreName' => $selectedNewStoreName,
            'accountChoices' => $this->accountChoices($accountRepository, $accountManager),
            'stores' => $storeRepository->findActiveForUser($this->getUser()),
            'storeManager' => $storeManager,
            'products' => $productRepository->findActiveForUser($this->getUser()),
            'productManager' => $productManager,
            'categories' => $categoryRepository->findActiveForUser($this->getUser()),
        ]);
    }

    #[Route('/receipts/{publicId}', name: 'receipt_show', methods: ['GET'])]
    public function receiptShow(
        string $publicId,
        CourseReceiptRepository $receiptRepository,
        CourseAttachmentRepository $attachmentRepository,
        CourseProductRepository $productRepository,
        CourseStoreManager $storeManager,
        CourseProductManager $productManager,
        BankAccountManager $accountManager,
        BankEncryptionService $encryption,
    ): Response {
        $receipt = $this->receiptOr404($publicId, $receiptRepository);

        $lineRows = [];
        foreach ($receipt->getLines() as $line) {
            $lineRows[] = [
                'publicId' => $line->getPublicId(),
                'rawLabel' => $encryption->decrypt($line->getEncryptedRawLabel()),
                'product' => $line->getProduct(),
                'productName' => $line->getProduct() !== null ? $productManager->name($line->getProduct()) : null,
                'categoryName' => $line->getCategory()?->getName(),
                'quantity' => $line->getQuantity(),
                'unit' => $line->getUnit(),
                'unitPrice' => $line->getUnitPrice(),
                'lineTotal' => $line->getLineTotal(),
            ];
        }

        return $this->render('courses/receipt_show.html.twig', [
            'receipt' => $receipt,
            'lineRows' => $lineRows,
            'attachments' => $attachmentRepository->findActiveForReceipt($receipt),
            'productChoices' => $this->productChoices($productRepository, $productManager),
            'storeManager' => $storeManager,
            'productManager' => $productManager,
            'accountManager' => $accountManager,
        ]);
    }

    #[Route('/receipts/{publicId}/lines/{linePublicId}/product', name: 'receipt_line_reassign_product', methods: ['POST'])]
    public function receiptLineReassignProduct(
        string $publicId,
        string $linePublicId,
        Request $request,
        CourseReceiptRepository $receiptRepository,
        CourseReceiptLineRepository $lineRepository,
        CourseProductRepository $productRepository,
        CourseReceiptReviewService $reviewService,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('courses_line_product_' . $linePublicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $receipt = $this->receiptOr404($publicId, $receiptRepository);
        $line = $lineRepository->findOneForUserByPublicId($this->getUser(), $linePublicId);
        $product = $productRepository->findOneForUserByPublicId($this->getUser(), (string) $request->request->get('product'));

        if ($line === null || $line->getReceipt() !== $receipt || $product === null) {
            throw $this->createNotFoundException();
        }

        try {
            $reviewService->reassignLineProduct($this->getUser(), $line, $product);
            $entityManager->flush();
            $this->addFlash('success', 'Le produit de cette ligne a ete corrige.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_courses_receipt_show', ['publicId' => $publicId]);
    }

    #[Route('/receipts/{publicId}/archive', name: 'receipt_archive', methods: ['POST'])]
    public function receiptArchive(string $publicId, Request $request, CourseReceiptRepository $receiptRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('courses_receipt_archive_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $receipt = $this->receiptOr404($publicId, $receiptRepository);
        $receipt->archive();
        $entityManager->flush();
        $this->addFlash('warning', 'Le ticket a ete archive.');

        return $this->redirectToRoute('app_courses_receipts');
    }

    #[Route('/stores', name: 'stores', methods: ['GET'])]
    public function stores(CourseStoreRepository $storeRepository, CourseStoreManager $storeManager): Response
    {
        return $this->render('courses/stores.html.twig', [
            'stores' => $storeRepository->findActiveForUser($this->getUser()),
            'storeManager' => $storeManager,
        ]);
    }

    #[Route('/stores/new', name: 'store_new', methods: ['GET', 'POST'])]
    public function storeNew(Request $request, CourseStoreManager $storeManager, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CourseStoreType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $storeManager->save($this->getUser(), new CourseStore(), $form->getData());
            $entityManager->flush();
            $this->addFlash('success', 'Le magasin a ete cree.');

            return $this->redirectToRoute('app_courses_stores');
        }

        return $this->render('courses/store_form.html.twig', ['title' => 'Nouveau magasin', 'form' => $form]);
    }

    #[Route('/stores/{publicId}/edit', name: 'store_edit', methods: ['GET', 'POST'])]
    public function storeEdit(string $publicId, Request $request, CourseStoreRepository $storeRepository, CourseStoreManager $storeManager, EntityManagerInterface $entityManager): Response
    {
        $store = $this->storeOr404($publicId, $storeRepository);
        $form = $this->createForm(CourseStoreType::class, [
            'name' => $storeManager->name($store),
            'chain' => $store->getChain(),
            'address' => $storeManager->address($store),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $storeManager->save($this->getUser(), $store, $form->getData());
            $entityManager->flush();
            $this->addFlash('success', 'Le magasin a ete modifie.');

            return $this->redirectToRoute('app_courses_stores');
        }

        return $this->render('courses/store_form.html.twig', ['title' => 'Modifier le magasin', 'form' => $form]);
    }

    #[Route('/stores/{publicId}/archive', name: 'store_archive', methods: ['POST'])]
    public function storeArchive(string $publicId, Request $request, CourseStoreRepository $storeRepository, CourseStoreManager $storeManager, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('courses_store_archive_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $storeManager->archive($this->getUser(), $this->storeOr404($publicId, $storeRepository));
        $entityManager->flush();
        $this->addFlash('warning', 'Le magasin a ete archive.');

        return $this->redirectToRoute('app_courses_stores');
    }

    #[Route('/products', name: 'products', methods: ['GET'])]
    public function products(CourseProductRepository $productRepository, CourseProductManager $productManager): Response
    {
        return $this->render('courses/products.html.twig', [
            'products' => $productRepository->findActiveForUser($this->getUser()),
            'productManager' => $productManager,
        ]);
    }

    #[Route('/products/new', name: 'product_new', methods: ['GET', 'POST'])]
    public function productNew(Request $request, CourseProductManager $productManager, CourseCategoryRepository $categoryRepository, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CourseProductType::class, ['defaultUnit' => CourseProduct::UNIT_PIECE], [
            'category_choices' => $this->categoryChoices($categoryRepository),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $productManager->save($this->getUser(), new CourseProduct(), $form->getData());
            $entityManager->flush();
            $this->addFlash('success', 'Le produit a ete cree.');

            return $this->redirectToRoute('app_courses_products');
        }

        return $this->render('courses/product_form.html.twig', ['title' => 'Nouveau produit', 'form' => $form]);
    }

    #[Route('/products/{publicId}/edit', name: 'product_edit', methods: ['GET', 'POST'])]
    public function productEdit(string $publicId, Request $request, CourseProductRepository $productRepository, CourseProductManager $productManager, CourseCategoryRepository $categoryRepository, EntityManagerInterface $entityManager): Response
    {
        $product = $this->productOr404($publicId, $productRepository);
        $form = $this->createForm(CourseProductType::class, [
            'name' => $productManager->name($product),
            'category' => $product->getCategory(),
            'defaultUnit' => $product->getDefaultUnit(),
            'packageQuantity' => $product->getPackageQuantity(),
        ], [
            'category_choices' => $this->categoryChoices($categoryRepository),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $productManager->save($this->getUser(), $product, $form->getData());
            $entityManager->flush();
            $this->addFlash('success', 'Le produit a ete modifie.');

            return $this->redirectToRoute('app_courses_products');
        }

        return $this->render('courses/product_form.html.twig', ['title' => 'Modifier le produit', 'form' => $form]);
    }

    #[Route('/products/{publicId}/archive', name: 'product_archive', methods: ['POST'])]
    public function productArchive(string $publicId, Request $request, CourseProductRepository $productRepository, CourseProductManager $productManager, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('courses_product_archive_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $productManager->archive($this->getUser(), $this->productOr404($publicId, $productRepository));
        $entityManager->flush();
        $this->addFlash('warning', 'Le produit a ete archive.');

        return $this->redirectToRoute('app_courses_products');
    }

    #[Route('/shopping-lists', name: 'shopping_lists', methods: ['GET'])]
    public function shoppingLists(CourseShoppingListRepository $shoppingListRepository): Response
    {
        return $this->render('courses/shopping_lists.html.twig', [
            'lists' => $shoppingListRepository->findActiveForUser($this->getUser()),
        ]);
    }

    #[Route('/shopping-lists/new', name: 'shopping_list_new', methods: ['GET', 'POST'])]
    public function shoppingListNew(Request $request, EntityManagerInterface $entityManager): Response
    {
        $list = new CourseShoppingList();
        $form = $this->createForm(CourseShoppingListType::class, $list);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $list->setUser($this->getUser());
            $entityManager->persist($list);
            $entityManager->flush();
            $this->addFlash('success', 'La liste de courses a ete creee.');

            return $this->redirectToRoute('app_courses_shopping_list_show', ['publicId' => $list->getPublicId()]);
        }

        return $this->render('courses/shopping_list_form.html.twig', ['form' => $form]);
    }

    #[Route('/shopping-lists/{publicId}', name: 'shopping_list_show', methods: ['GET'])]
    public function shoppingListShow(
        string $publicId,
        CourseShoppingListRepository $shoppingListRepository,
        CourseProductRepository $productRepository,
        CourseProductManager $productManager,
        CourseShoppingListOptimizer $optimizer,
    ): Response {
        $list = $this->shoppingListOr404($publicId, $shoppingListRepository);
        $itemForm = $this->createForm(CourseShoppingListItemType::class, null, [
            'product_choices' => $this->productChoices($productRepository, $productManager),
        ]);

        return $this->render('courses/shopping_list_show.html.twig', [
            'list' => $list,
            'itemForm' => $itemForm,
            'productManager' => $productManager,
            'bestSingleStore' => $list->getItems()->count() > 0 ? $optimizer->bestSingleStore($this->getUser(), $list) : null,
            'multiStoreSplit' => $list->getItems()->count() > 0 ? $optimizer->multiStoreSplit($this->getUser(), $list) : null,
        ]);
    }

    #[Route('/shopping-lists/{publicId}/items', name: 'shopping_list_item_add', methods: ['POST'])]
    public function shoppingListItemAdd(
        string $publicId,
        Request $request,
        CourseShoppingListRepository $shoppingListRepository,
        CourseProductRepository $productRepository,
        CourseProductManager $productManager,
        EntityManagerInterface $entityManager,
    ): Response {
        $list = $this->shoppingListOr404($publicId, $shoppingListRepository);
        $form = $this->createForm(CourseShoppingListItemType::class, null, [
            'product_choices' => $this->productChoices($productRepository, $productManager),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $item = (new CourseShoppingListItem())
                ->setProduct($data['product'])
                ->setDesiredQuantity($data['desiredQuantity'])
                ->setUnit($data['unit']);
            $list->addItem($item);
            $entityManager->flush();
            $this->addFlash('success', 'Article ajoute a la liste.');
        } else {
            $this->addFlash('error', 'Choisissez un produit valide pour l\'ajouter a la liste.');
        }

        return $this->redirectToRoute('app_courses_shopping_list_show', ['publicId' => $publicId]);
    }

    #[Route('/shopping-lists/{publicId}/items/{itemPublicId}/toggle', name: 'shopping_list_item_toggle', methods: ['POST'])]
    public function shoppingListItemToggle(string $publicId, string $itemPublicId, Request $request, CourseShoppingListRepository $shoppingListRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('courses_list_item_toggle_' . $itemPublicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $list = $this->shoppingListOr404($publicId, $shoppingListRepository);
        foreach ($list->getItems() as $item) {
            if ($item->getPublicId() === $itemPublicId) {
                $item->setChecked(!$item->isChecked());
                break;
            }
        }
        $entityManager->flush();

        return $this->redirectToRoute('app_courses_shopping_list_show', ['publicId' => $publicId]);
    }

    #[Route('/shopping-lists/{publicId}/items/{itemPublicId}/remove', name: 'shopping_list_item_remove', methods: ['POST'])]
    public function shoppingListItemRemove(string $publicId, string $itemPublicId, Request $request, CourseShoppingListRepository $shoppingListRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('courses_list_item_remove_' . $itemPublicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $list = $this->shoppingListOr404($publicId, $shoppingListRepository);
        foreach ($list->getItems() as $item) {
            if ($item->getPublicId() === $itemPublicId) {
                $list->removeItem($item);
                $entityManager->remove($item);
                break;
            }
        }
        $entityManager->flush();

        return $this->redirectToRoute('app_courses_shopping_list_show', ['publicId' => $publicId]);
    }

    #[Route('/shopping-lists/{publicId}/archive', name: 'shopping_list_archive', methods: ['POST'])]
    public function shoppingListArchive(string $publicId, Request $request, CourseShoppingListRepository $shoppingListRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('courses_list_archive_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $this->shoppingListOr404($publicId, $shoppingListRepository)->archive();
        $entityManager->flush();
        $this->addFlash('warning', 'La liste de courses a ete archivee.');

        return $this->redirectToRoute('app_courses_shopping_lists');
    }

    /**
     * @param array<int,array{rawLabel?:string,product?:string,newProductName?:string,category?:string,quantity?:mixed,unit?:string,unitPrice?:mixed,lineTotal?:mixed}> $rawLines
     * @return array<int,array{rawLabel:string,product:?CourseProduct,newProductName:string,category:?\App\Entity\CourseCategory,quantity:string,unit:string,unitPrice:string,lineTotal:string,confidence:?string}>
     */
    private function lineRowsFromSubmission(array $rawLines, CourseProductRepository $productRepository, CourseCategoryRepository $categoryRepository): array
    {
        $rows = [];
        foreach ($rawLines as $row) {
            $productPublicId = trim((string) ($row['product'] ?? ''));
            $categoryPublicId = trim((string) ($row['category'] ?? ''));
            $rows[] = [
                'rawLabel' => (string) ($row['rawLabel'] ?? ''),
                'product' => $productPublicId !== '' ? $productRepository->findOneForUserByPublicId($this->getUser(), $productPublicId) : null,
                'newProductName' => (string) ($row['newProductName'] ?? ''),
                'category' => $categoryPublicId !== '' ? $categoryRepository->findOneForUserByPublicId($this->getUser(), $categoryPublicId) : null,
                'quantity' => (string) ($row['quantity'] ?? ''),
                'unit' => (string) ($row['unit'] ?? CourseProduct::UNIT_PIECE),
                'unitPrice' => (string) ($row['unitPrice'] ?? ''),
                'lineTotal' => (string) ($row['lineTotal'] ?? ''),
                'confidence' => null,
            ];
        }

        return $rows;
    }

    private function receiptOr404(string $publicId, CourseReceiptRepository $repository): CourseReceipt
    {
        $receipt = $repository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$receipt instanceof CourseReceipt) {
            throw $this->createNotFoundException();
        }

        return $receipt;
    }

    private function storeOr404(string $publicId, CourseStoreRepository $repository): CourseStore
    {
        $store = $repository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$store instanceof CourseStore) {
            throw $this->createNotFoundException();
        }

        return $store;
    }

    private function productOr404(string $publicId, CourseProductRepository $repository): CourseProduct
    {
        $product = $repository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$product instanceof CourseProduct) {
            throw $this->createNotFoundException();
        }

        return $product;
    }

    private function shoppingListOr404(string $publicId, CourseShoppingListRepository $repository): CourseShoppingList
    {
        $list = $repository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$list instanceof CourseShoppingList) {
            throw $this->createNotFoundException();
        }

        return $list;
    }

    private function accountChoices(BankAccountRepository $accountRepository, BankAccountManager $accountManager): array
    {
        $choices = [];
        foreach ($accountRepository->findActiveForUser($this->getUser()) as $account) {
            $label = $accountManager->displayName($account);
            $choices[$label !== '' ? $label : 'Compte ' . substr($account->getPublicId(), -6)] = $account;
        }

        return $choices;
    }

    private function categoryChoices(CourseCategoryRepository $categoryRepository): array
    {
        $choices = [];
        foreach ($categoryRepository->findActiveForUser($this->getUser()) as $category) {
            $choices[$category->getName()] = $category;
        }

        return $choices;
    }

    private function productChoices(CourseProductRepository $productRepository, CourseProductManager $productManager): array
    {
        $choices = [];
        foreach ($productRepository->findActiveForUser($this->getUser()) as $product) {
            $choices[$productManager->name($product)] = $product;
        }

        return $choices;
    }

    private function profileChoices(CourseImportProfileRepository $profileRepository): array
    {
        $choices = [];
        foreach ($profileRepository->findAllForUser($this->getUser()) as $profile) {
            $choices[$profile->getName()] = $profile;
        }

        return $choices;
    }

    private function accountFromRequest(Request $request, string $key, BankAccountRepository $repository): ?BankAccount
    {
        $value = (string) $request->request->get($key, '');

        return $value !== '' ? $repository->findOneForUserByPublicId($this->getUser(), $value) : null;
    }
}
