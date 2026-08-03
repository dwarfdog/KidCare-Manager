<?php

namespace App\Controller;

use App\Entity\BankAccount;
use App\Entity\BankBudget;
use App\Entity\BankCategory;
use App\Entity\BankGoal;
use App\Entity\BankImportProfile;
use App\Entity\BankRecurringRule;
use App\Entity\BankTransaction;
use App\Form\BankAccountType;
use App\Form\BankAttachmentType;
use App\Form\BankBudgetType;
use App\Form\BankCategoryType;
use App\Form\BankGoalType;
use App\Form\BankImportType;
use App\Form\BankRecurringRuleType;
use App\Form\BankTransactionType;
use App\Form\BankTransferType;
use App\Model\BankTransactionListCriteria;
use App\Repository\BankAccountRepository;
use App\Repository\BankAttachmentRepository;
use App\Repository\BankBudgetRepository;
use App\Repository\BankCategoryRepository;
use App\Repository\BankGoalRepository;
use App\Repository\BankImportBatchRepository;
use App\Repository\BankRecurringRuleRepository;
use App\Repository\BankTransactionRepository;
use App\Service\BankAccountManager;
use App\Service\BankAttachmentManager;
use App\Service\BankBudgetCalculator;
use App\Service\BankDashboardSynchronizer;
use App\Service\BankEncryptionService;
use App\Service\BankExportService;
use App\Service\BankGoalCalculator;
use App\Service\BankImportService;
use App\Service\BankLoanCalculator;
use App\Service\BankLoanScheduleGenerator;
use App\Service\BankRecurringTransactionGenerator;
use App\Service\BankStatisticsService;
use App\Service\BankTransactionManager;
use App\Service\BankTransferManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/bank', name: 'app_bank_')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class BankController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        BankDashboardSynchronizer $synchronizer,
        BankTransactionManager $transactionManager,
        BankStatisticsService $statistics,
        BankTransactionRepository $transactionRepository,
        BankBudgetCalculator $budgetCalculator,
        BankGoalRepository $goalRepository,
        BankGoalCalculator $goalCalculator,
        BankAccountManager $accountManager,
    ): Response {
        $user = $this->getUser();
        $synchronizer->synchronize($user);
        $goals = $goalRepository->findActiveForUser($user);

        return $this->render('bank/index.html.twig', [
            'dashboard' => $statistics->dashboard($user),
            'recentTransactions' => $transactionRepository->findRecentForUser($user),
            'budgetRows' => $budgetCalculator->rows($user),
            'goalRows' => $goalCalculator->rows($goals),
            'transactionManager' => $transactionManager,
            'accountManager' => $accountManager,
        ]);
    }

    #[Route('/accounts', name: 'accounts', methods: ['GET'])]
    public function accounts(BankAccountRepository $accountRepository, BankAccountManager $accountManager): Response
    {
        return $this->render('bank/accounts.html.twig', [
            'accounts' => $accountRepository->findActiveForUser($this->getUser()),
            'accountManager' => $accountManager,
        ]);
    }

    #[Route('/accounts/new', name: 'account_new', methods: ['GET', 'POST'])]
    public function accountNew(Request $request, BankAccountManager $accountManager, BankAccountRepository $accountRepository, BankCategoryRepository $categoryRepository, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BankAccountType::class, [
            'initialBalance' => 0,
            'initialBalanceDate' => new \DateTime('today'),
            'type' => BankAccount::TYPE_CURRENT,
        ], [
            'debit_account_choices' => $this->debitAccountChoices($accountRepository, $accountManager),
            'loan_category_choices' => $this->expenseCategoryChoices($categoryRepository),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $accountManager->save($this->getUser(), new BankAccount(), $form->getData());
            $entityManager->flush();
            $this->addFlash('success', 'Le compte bancaire a ete cree.');

            return $this->redirectToRoute('app_bank_accounts');
        }

        return $this->render('bank/account_form.html.twig', ['title' => 'Nouveau compte', 'form' => $form]);
    }

    #[Route('/accounts/{publicId}/edit', name: 'account_edit', methods: ['GET', 'POST'])]
    public function accountEdit(
        string $publicId,
        Request $request,
        BankAccountRepository $accountRepository,
        BankAccountManager $accountManager,
        BankCategoryRepository $categoryRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $account = $this->accountOr404($publicId, $accountRepository);
        $form = $this->createForm(BankAccountType::class, [
            'name' => $accountManager->displayName($account),
            'iban' => $accountManager->displayIban($account),
            'type' => $account->getType(),
            'initialBalance' => $account->getInitialBalance(),
            'initialBalanceDate' => $account->getInitialBalanceDate(),
            'loanInitialCapital' => $account->getLoanInitialCapital(),
            'loanRemainingCapital' => $account->getLoanRemainingCapital(),
            'loanAnnualRate' => $account->getLoanAnnualRate(),
            'loanEndDate' => $account->getLoanEndDate(),
            'loanMonthlyPayment' => $account->getLoanMonthlyPayment(),
            'loanPaymentDay' => $account->getLoanPaymentDay(),
            'loanDebitAccount' => $account->getLoanDebitAccount(),
            'loanCategory' => $account->getLoanCategory(),
        ], [
            'debit_account_choices' => $this->debitAccountChoices($accountRepository, $accountManager, $account),
            'loan_category_choices' => $this->expenseCategoryChoices($categoryRepository),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $accountManager->save($this->getUser(), $account, $form->getData());
            $entityManager->flush();
            $this->addFlash('success', 'Le compte bancaire a ete modifie.');

            return $this->redirectToRoute('app_bank_accounts');
        }

        return $this->render('bank/account_form.html.twig', ['title' => 'Modifier le compte', 'form' => $form]);
    }

    #[Route('/accounts/{publicId}', name: 'account_show', methods: ['GET'])]
    public function accountShow(
        string $publicId,
        Request $request,
        BankAccountRepository $accountRepository,
        BankAccountManager $accountManager,
        BankTransactionRepository $transactionRepository,
        BankCategoryRepository $categoryRepository,
        BankTransactionManager $transactionManager,
        BankStatisticsService $statistics,
    ): Response {
        $account = $this->accountOr404($publicId, $accountRepository);
        $referenceDate = $statistics->nextSalaryDate($this->getUser(), new \DateTimeImmutable('today'));
        // La periode courante s'arrete la veille du prochain salaire (le jour de reference ouvre la suivante).
        $periodEnd = $referenceDate?->modify('-1 day');

        $listData = $this->accountTransactionListData(
            $request,
            $account,
            $periodEnd,
            $transactionRepository,
            $categoryRepository,
        );

        return $this->render('bank/account_show.html.twig', $listData + [
            'account' => $account,
            'balance' => $accountManager->balance($account),
            'projectedBalance' => $accountManager->projectedBalance($account, $periodEnd),
            'plannedTotal' => $periodEnd !== null ? $accountManager->plannedNetUntil($account, $periodEnd) : null,
            'referenceDate' => $referenceDate,
            'periodEnd' => $periodEnd,
            'accountManager' => $accountManager,
            'transactionManager' => $transactionManager,
        ]);
    }

    #[Route('/accounts/{publicId}/transactions/{section}', name: 'account_transactions', requirements: ['section' => 'real|planned'], methods: ['GET'])]
    public function accountTransactions(
        string $publicId,
        string $section,
        Request $request,
        BankAccountRepository $accountRepository,
        BankTransactionRepository $transactionRepository,
        BankCategoryRepository $categoryRepository,
        BankTransactionManager $transactionManager,
        BankStatisticsService $statistics,
    ): Response {
        $account = $this->accountOr404($publicId, $accountRepository);
        $referenceDate = $statistics->nextSalaryDate($this->getUser(), new \DateTimeImmutable('today'));
        $periodEnd = $referenceDate?->modify('-1 day');
        $data = $this->accountTransactionListData($request, $account, $periodEnd, $transactionRepository, $categoryRepository) + [
            'account' => $account,
            'referenceDate' => $referenceDate,
            'periodEnd' => $periodEnd,
            'transactionManager' => $transactionManager,
        ];

        return $this->render($section === 'real' ? 'bank/_account_real_transactions.html.twig' : 'bank/_account_planned_transactions.html.twig', $data);
    }

    #[Route('/accounts/{publicId}/archive', name: 'account_archive', methods: ['POST'])]
    public function accountArchive(string $publicId, Request $request, BankAccountRepository $accountRepository, BankAccountManager $accountManager, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('bank_account_archive_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $accountManager->archive($this->getUser(), $this->accountOr404($publicId, $accountRepository));
        $entityManager->flush();
        $this->addFlash('warning', 'Le compte bancaire a été archivé pour mémoire.');

        return $this->redirectToRoute('app_bank_accounts');
    }

    #[Route('/loans', name: 'loans', methods: ['GET'])]
    public function loans(BankLoanScheduleGenerator $loanScheduleGenerator, BankTransactionManager $transactionManager, BankLoanCalculator $loanCalculator, BankAccountManager $accountManager, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $loanScheduleGenerator->generateForUser($user);
        $transactionManager->realizeDuePlanned($user);
        $entityManager->flush();

        return $this->render('bank/loans.html.twig', [
            'overview' => $loanCalculator->overview($user),
            'accountManager' => $accountManager,
        ]);
    }

    #[Route('/archives', name: 'archives', methods: ['GET'])]
    public function archives(): Response
    {
        return $this->redirectToRoute('app_archives', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/transactions/new', name: 'transaction_new', methods: ['GET', 'POST'])]
    public function transactionNew(Request $request, BankTransactionManager $manager, BankTransferManager $transferManager, EntityManagerInterface $entityManager, BankAccountRepository $accountRepository, BankCategoryRepository $categoryRepository, BankAccountManager $accountManager): Response
    {
        $choices = $this->choices($accountRepository, $categoryRepository, $accountManager);
        $form = $this->createForm(BankTransactionType::class, [
            'account' => $this->accountFromQuery($request, 'account', $accountRepository),
            'date' => new \DateTime('today'),
            'status' => BankTransaction::STATUS_REAL,
            'type' => BankTransaction::TYPE_EXPENSE,
        ], $choices + ['allow_internal_transfer' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                if (($data['type'] ?? null) === BankTransactionManager::TYPE_INTERNAL_TRANSFER) {
                    [$createdTransaction] = $transferManager->create(
                        $this->getUser(),
                        $data['account'],
                        $data['targetAccount'],
                        (float) $data['amount'],
                        $data['date'],
                        $data['label'],
                        $categoryRepository->findOneByName($this->getUser(), 'Virement interne'),
                        (string) ($data['status'] ?? BankTransaction::STATUS_REAL),
                    );
                } else {
                    $createdTransaction = $manager->save($this->getUser(), new BankTransaction(), $data);
                }
                $entityManager->flush();
                $this->addFlash('success', 'Operation bancaire creee.');

                return $this->redirectToRoute('app_bank_account_show', [
                    'publicId' => $createdTransaction->getAccount()->getPublicId(),
                    'focus' => $createdTransaction->getPublicId(),
                ]);
            } catch (\InvalidArgumentException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('bank/transaction_form.html.twig', ['title' => 'Nouvelle operation', 'form' => $form]);
    }

    #[Route('/transactions', name: 'transactions', methods: ['GET'])]
    public function transactions(Request $request, BankTransactionRepository $transactionRepository, BankTransactionManager $transactionManager): Response
    {
        $from = $this->dateFromQuery($request, 'from', new \DateTimeImmutable('first day of this month'));
        $to = $this->dateFromQuery($request, 'to', new \DateTimeImmutable('last day of this month'));

        return $this->render('bank/transactions.html.twig', [
            'transactions' => $transactionRepository->findForPeriod($this->getUser(), $from, $to),
            'transactionManager' => $transactionManager,
            'from' => $from,
            'to' => $to,
        ]);
    }

    #[Route('/transactions/{publicId}/edit', name: 'transaction_edit', methods: ['GET', 'POST'])]
    public function transactionEdit(
        string $publicId,
        Request $request,
        BankTransactionRepository $transactionRepository,
        BankTransactionManager $manager,
        BankTransferManager $transferManager,
        BankAttachmentRepository $attachmentRepository,
        BankAccountRepository $accountRepository,
        BankCategoryRepository $categoryRepository,
        BankAccountManager $accountManager,
        BankAttachmentManager $attachmentManager,
        EntityManagerInterface $entityManager,
    ): Response {
        $transaction = $this->transactionOr404($publicId, $transactionRepository);
        $choices = $this->choices($accountRepository, $categoryRepository, $accountManager);
        $isInternalTransfer = $transaction->getSource() === BankTransaction::SOURCE_TRANSFER && $transaction->getMirrorTransaction() !== null;
        $sourceAccount = $transaction->getType() === BankTransaction::TYPE_TRANSFER_IN && $transaction->getMirrorTransaction() !== null
            ? $transaction->getMirrorTransaction()->getAccount()
            : $transaction->getAccount();
        $targetAccount = $transaction->getType() === BankTransaction::TYPE_TRANSFER_OUT && $transaction->getMirrorTransaction() !== null
            ? $transaction->getMirrorTransaction()->getAccount()
            : ($isInternalTransfer ? $transaction->getAccount() : null);
        $form = $this->createForm(BankTransactionType::class, [
            'account' => $sourceAccount,
            'targetAccount' => $targetAccount,
            'category' => $transaction->getCategory(),
            'type' => $isInternalTransfer ? BankTransactionManager::TYPE_INTERNAL_TRANSFER : ($transaction->getAmount() >= 0 ? BankTransaction::TYPE_INCOME : BankTransaction::TYPE_EXPENSE),
            'date' => $transaction->getDate(),
            'label' => $manager->label($transaction),
            'amount' => $transaction->getAbsoluteAmount(),
            'status' => $transaction->getStatus() === BankTransaction::STATUS_RECONCILED ? BankTransaction::STATUS_REAL : $transaction->getStatus(),
        ], $choices + ['allow_internal_transfer' => $isInternalTransfer]);
        $attachmentForm = $this->createForm(BankAttachmentType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                if (($data['type'] ?? null) === BankTransactionManager::TYPE_INTERNAL_TRANSFER) {
                    $transferManager->updatePair(
                        $this->getUser(),
                        $transaction,
                        $data['account'],
                        $data['targetAccount'],
                        (float) $data['amount'],
                        $data['date'],
                        $data['label'],
                        $categoryRepository->findOneByName($this->getUser(), 'Virement interne'),
                        (string) ($data['status'] ?? BankTransaction::STATUS_REAL),
                    );
                } else {
                    $manager->save($this->getUser(), $transaction, $data);
                }
                $entityManager->flush();
                $this->addFlash('success', 'Operation bancaire modifiee.');

                return $this->redirectToRoute('app_bank_transaction_edit', ['publicId' => $transaction->getPublicId()]);
            } catch (\InvalidArgumentException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('bank/transaction_edit.html.twig', [
            'transaction' => $transaction,
            'transactionManager' => $manager,
            'form' => $form,
            'isInternalTransfer' => $isInternalTransfer,
            'attachmentForm' => $attachmentForm,
            'attachments' => $attachmentRepository->findActiveForTransaction($transaction),
            'attachmentManager' => $attachmentManager,
        ]);
    }

    #[Route('/transactions/{publicId}/archive', name: 'transaction_archive', methods: ['POST'])]
    public function transactionArchive(string $publicId, Request $request, BankTransactionRepository $transactionRepository, BankTransactionManager $manager, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('bank_transaction_archive_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $manager->archive($this->getUser(), $this->transactionOr404($publicId, $transactionRepository));
        $entityManager->flush();
        $this->addFlash('warning', 'Opération bancaire archivée pour mémoire.');

        return $this->redirectToRoute('app_bank_index');
    }

    #[Route('/transactions/{publicId}/verification', name: 'transaction_verification', methods: ['POST'])]
    public function transactionVerification(string $publicId, Request $request, BankTransactionRepository $transactionRepository, BankTransactionManager $manager, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('bank_transaction_verify_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $transaction = $this->transactionOr404($publicId, $transactionRepository);
        try {
            $manager->setVerification($this->getUser(), $transaction, (bool) $request->request->get('verified'));
            $entityManager->flush();
            $this->addFlash($transaction->isVerified() ? 'success' : 'warning', $transaction->isVerified() ? 'Operation verifiee.' : 'Operation marquee non verifiee.');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        $parameters = ['publicId' => $transaction->getAccount()->getPublicId(), 'focus' => $transaction->getPublicId()];
        if ($request->request->get('return_context') === 'account') {
            $parameters += $this->sanitizeAccountListParameters($request->request->all('return'));
        }

        return $this->redirectToRoute('app_bank_account_show', $parameters);
    }

    #[Route('/transfers/new', name: 'transfer_new', methods: ['GET', 'POST'])]
    public function transferNew(Request $request, BankTransferManager $transferManager, BankAccountRepository $accountRepository, BankCategoryRepository $categoryRepository, BankAccountManager $accountManager, EntityManagerInterface $entityManager): Response
    {
        $accountChoices = $this->accountChoices($accountRepository, $accountManager);
        $form = $this->createForm(BankTransferType::class, ['date' => new \DateTime('today')], ['account_choices' => $accountChoices]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                [$sourceTransaction] = $transferManager->create($this->getUser(), $data['source'], $data['target'], (float) $data['amount'], $data['date'], $data['label'], $categoryRepository->findOneByName($this->getUser(), 'Virement interne'));
                $entityManager->flush();
                $this->addFlash('success', 'Virement interne cree.');

                return $this->redirectToRoute('app_bank_account_show', [
                    'publicId' => $sourceTransaction->getAccount()->getPublicId(),
                    'focus' => $sourceTransaction->getPublicId(),
                ]);
            } catch (\InvalidArgumentException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('bank/form.html.twig', ['title' => 'Nouveau virement interne', 'form' => $form]);
    }

    #[Route('/recurring', name: 'recurring', methods: ['GET', 'POST'])]
    public function recurring(Request $request, BankRecurringRuleRepository $ruleRepository, BankAccountRepository $accountRepository, BankCategoryRepository $categoryRepository, BankAccountManager $accountManager, BankEncryptionService $encryption, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BankRecurringRuleType::class, [
            'startDate' => new \DateTime('today'),
            'type' => BankTransaction::TYPE_EXPENSE,
            'frequency' => BankRecurringRule::FREQUENCY_MONTHLY,
        ], $this->choices($accountRepository, $categoryRepository, $accountManager));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $amount = $data['type'] === BankTransaction::TYPE_EXPENSE ? -abs((float) $data['amount']) : abs((float) $data['amount']);
            $rule = (new BankRecurringRule())
                ->setUser($this->getUser())
                ->setAccount($data['account'])
                ->setCategory($data['category'] ?? null)
                ->setEncryptedLabel($encryption->encrypt($data['label']) ?? '')
                ->setSignedAmount($amount)
                ->setFrequency($data['frequency'])
                ->setIntervalDays($data['intervalDays'] ?? null)
                ->setStartDate($data['startDate'])
                ->setEndDate($data['endDate'] ?? null)
                ->setSalaryReference((bool) ($data['isSalaryReference'] ?? false) && $amount >= 0)
                ->setPeriodAttachment($amount >= 0 ? (string) ($data['periodAttachment'] ?? BankRecurringRule::ATTACHMENT_CURRENT) : BankRecurringRule::ATTACHMENT_CURRENT);
            $entityManager->persist($rule);
            $entityManager->flush();
            $this->addFlash('success', 'Regle recurrente creee.');

            return $this->redirectToRoute('app_bank_recurring');
        }

        // Seules les recurrences actives sont listees ici; les desactivees vivent dans l'onglet Archives.
        $filters = [
            'account' => $this->accountFromQuery($request, 'account', $accountRepository),
            'category' => $this->categoryFromQuery($request, 'category', $categoryRepository),
            'direction' => in_array($request->query->get('direction'), ['income', 'expense'], true) ? $request->query->get('direction') : null,
            'active' => true,
        ];

        return $this->render('bank/recurring.html.twig', [
            'rules' => $ruleRepository->findFilteredForUser($this->getUser(), $filters),
            'form' => $form,
            'encryption' => $encryption,
            'accounts' => $accountRepository->findActiveForUser($this->getUser()),
            'categories' => $categoryRepository->findActiveForUser($this->getUser()),
            'accountManager' => $accountManager,
            'filters' => [
                'account' => $request->query->get('account', ''),
                'category' => $request->query->get('category', ''),
                'direction' => $request->query->get('direction', ''),
            ],
        ]);
    }

    #[Route('/recurring/{publicId}/edit', name: 'recurring_edit', methods: ['GET', 'POST'])]
    public function recurringEdit(string $publicId, Request $request, BankRecurringRuleRepository $ruleRepository, BankAccountRepository $accountRepository, BankCategoryRepository $categoryRepository, BankAccountManager $accountManager, BankEncryptionService $encryption, BankTransactionManager $transactionManager, BankRecurringTransactionGenerator $generator, EntityManagerInterface $entityManager): Response
    {
        $rule = $ruleRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$rule instanceof BankRecurringRule) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(BankRecurringRuleType::class, [
            'account' => $rule->getAccount(),
            'category' => $rule->getCategory(),
            'type' => $rule->getAmount() >= 0 ? BankTransaction::TYPE_INCOME : BankTransaction::TYPE_EXPENSE,
            'label' => $encryption->decrypt($rule->getEncryptedLabel()),
            'amount' => abs($rule->getAmount()),
            'frequency' => $rule->getFrequency(),
            'intervalDays' => $rule->getIntervalDays(),
            'startDate' => $rule->getStartDate(),
            'endDate' => $rule->getEndDate(),
            'isSalaryReference' => $rule->isSalaryReference(),
            'periodAttachment' => $rule->getPeriodAttachment(),
        ], $this->choices($accountRepository, $categoryRepository, $accountManager));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $amount = $data['type'] === BankTransaction::TYPE_EXPENSE ? -abs((float) $data['amount']) : abs((float) $data['amount']);
            $archived = $transactionManager->archivePlannedForRule($this->getUser(), $rule);
            $rule
                ->setAccount($data['account'])
                ->setCategory($data['category'] ?? null)
                ->setEncryptedLabel($encryption->encrypt($data['label']) ?? '')
                ->setSignedAmount($amount)
                ->setFrequency($data['frequency'])
                ->setIntervalDays($data['intervalDays'] ?? null)
                ->setStartDate($data['startDate'])
                ->setEndDate($data['endDate'] ?? null)
                ->setSalaryReference((bool) ($data['isSalaryReference'] ?? false) && $amount >= 0)
                ->setPeriodAttachment($amount >= 0 ? (string) ($data['periodAttachment'] ?? BankRecurringRule::ATTACHMENT_CURRENT) : BankRecurringRule::ATTACHMENT_CURRENT);
            $entityManager->flush();
            $created = $generator->generateForRule($this->getUser(), $rule);
            $entityManager->flush();
            $this->addFlash('success', sprintf('Recurrence modifiee. %d prevision(s) remplacee(s), %d nouvelle(s) generee(s).', $archived, $created));

            return $this->redirectToRoute('app_bank_recurring');
        }

        return $this->render('bank/form.html.twig', ['title' => 'Modifier la recurrence', 'form' => $form]);
    }

    #[Route('/recurring/{publicId}/deactivate', name: 'recurring_deactivate', methods: ['POST'])]
    public function recurringDeactivate(string $publicId, Request $request, BankRecurringRuleRepository $ruleRepository, BankTransactionManager $transactionManager, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('bank_recurring_deactivate_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $rule = $ruleRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$rule instanceof BankRecurringRule) {
            throw $this->createNotFoundException();
        }

        $rule->setActive(false);
        // Les occurrences prevues ne doivent plus etre comptees.
        $transactionManager->archivePlannedForRule($this->getUser(), $rule);
        $entityManager->flush();
        $this->addFlash('warning', 'Recurrence desactivee et echeances prevues retirees du budget. Disponible dans les Archives.');

        return $this->redirectToRoute('app_bank_recurring');
    }

    #[Route('/categories/{publicId}/edit', name: 'category_edit', methods: ['GET', 'POST'])]
    public function categoryEdit(string $publicId, Request $request, BankCategoryRepository $categoryRepository, EntityManagerInterface $entityManager): Response
    {
        $category = $categoryRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$category instanceof BankCategory) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(BankCategoryType::class, $category);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Categorie modifiee.');

            return $this->redirectToRoute('app_bank_categories');
        }

        return $this->render('bank/form.html.twig', ['title' => 'Modifier la categorie', 'form' => $form]);
    }

    #[Route('/categories/{publicId}/archive', name: 'category_archive', methods: ['POST'])]
    public function categoryArchive(string $publicId, Request $request, BankCategoryRepository $categoryRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('bank_category_archive_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $category = $categoryRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$category instanceof BankCategory) {
            throw $this->createNotFoundException();
        }

        $category->archive();
        $entityManager->flush();
        $this->addFlash('warning', 'Categorie archivee.');

        return $this->redirectToRoute('app_bank_categories');
    }

    #[Route('/categories', name: 'categories', methods: ['GET', 'POST'])]
    public function categories(Request $request, BankCategoryRepository $categoryRepository, EntityManagerInterface $entityManager): Response
    {
        $category = (new BankCategory())->setUser($this->getUser())->setColor('#0f766e')->setIcon('tag');
        $form = $this->createForm(BankCategoryType::class, $category);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($category);
            $entityManager->flush();
            $this->addFlash('success', 'Categorie creee.');

            return $this->redirectToRoute('app_bank_categories');
        }

        $analyticsCategories = $categoryRepository->findActiveWithRealDataForUser($this->getUser());

        return $this->render('bank/categories.html.twig', [
            'categories' => $categoryRepository->findActiveForUser($this->getUser()),
            'analyticsCategoryIds' => array_map(static fn(BankCategory $item): string => $item->getPublicId(), $analyticsCategories),
            'form' => $form,
        ]);
    }

    #[Route('/budgets', name: 'budgets', methods: ['GET', 'POST'])]
    public function budgets(Request $request, BankBudgetCalculator $budgetCalculator, BankCategoryRepository $categoryRepository, EntityManagerInterface $entityManager): Response
    {
        $budget = (new BankBudget())->setUser($this->getUser());
        $form = $this->createForm(BankBudgetType::class, $budget, ['category_choices' => $this->categoryChoices($categoryRepository)]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($budget);
            $entityManager->flush();
            $this->addFlash('success', 'Budget cree.');

            return $this->redirectToRoute('app_bank_budgets');
        }

        return $this->render('bank/budgets.html.twig', ['budgetRows' => $budgetCalculator->rows($this->getUser()), 'form' => $form]);
    }

    #[Route('/budgets/{publicId}/edit', name: 'budget_edit', methods: ['GET', 'POST'])]
    public function budgetEdit(string $publicId, Request $request, BankBudgetRepository $budgetRepository, BankCategoryRepository $categoryRepository, EntityManagerInterface $entityManager): Response
    {
        $budget = $budgetRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$budget instanceof BankBudget) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(BankBudgetType::class, $budget, ['category_choices' => $this->categoryChoices($categoryRepository)]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Budget modifie.');

            return $this->redirectToRoute('app_bank_budgets');
        }

        return $this->render('bank/budget_form.html.twig', ['title' => 'Modifier le budget', 'form' => $form]);
    }

    #[Route('/budgets/{publicId}/archive', name: 'budget_archive', methods: ['POST'])]
    public function budgetArchive(string $publicId, Request $request, BankBudgetRepository $budgetRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('bank_budget_archive_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $budget = $budgetRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$budget instanceof BankBudget) {
            throw $this->createNotFoundException();
        }

        $budget->archive();
        $entityManager->flush();
        $this->addFlash('warning', 'Budget archive.');

        return $this->redirectToRoute('app_bank_budgets');
    }

    #[Route('/goals', name: 'goals', methods: ['GET', 'POST'])]
    public function goals(Request $request, BankGoalRepository $goalRepository, BankGoalCalculator $goalCalculator, BankAccountRepository $accountRepository, BankAccountManager $accountManager, EntityManagerInterface $entityManager): Response
    {
        $goal = (new BankGoal())->setUser($this->getUser());
        $form = $this->createForm(BankGoalType::class, $goal, ['account_choices' => $this->goalAccountChoices($accountRepository, $accountManager)]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($goal);
            $entityManager->flush();
            $this->addFlash('success', 'Objectif cree.');

            return $this->redirectToRoute('app_bank_goals');
        }

        $goals = $goalRepository->findActiveForUser($this->getUser());
        if ($goalCalculator->syncStatuses($goals)) {
            $entityManager->flush();
        }

        return $this->render('bank/goals.html.twig', [
            'goalRows' => $goalCalculator->rows($goals),
            'form' => $form,
            'accountManager' => $accountManager,
        ]);
    }

    #[Route('/goals/{publicId}/edit', name: 'goal_edit', methods: ['GET', 'POST'])]
    public function goalEdit(string $publicId, Request $request, BankGoalRepository $goalRepository, BankGoalCalculator $goalCalculator, BankAccountRepository $accountRepository, BankAccountManager $accountManager, EntityManagerInterface $entityManager): Response
    {
        $goal = $goalRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$goal instanceof BankGoal) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(BankGoalType::class, $goal, ['account_choices' => $this->goalAccountChoices($accountRepository, $accountManager, $goal->getLinkedAccount())]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $goalCalculator->syncStatuses([$goal]);
            $entityManager->flush();
            $this->addFlash('success', 'Objectif modifie.');

            return $this->redirectToRoute('app_bank_goals');
        }

        return $this->render('bank/goal_form.html.twig', ['title' => 'Modifier l\'objectif', 'form' => $form]);
    }

    #[Route('/goals/{publicId}/feed', name: 'goal_feed', methods: ['POST'])]
    public function goalFeed(string $publicId, Request $request, BankGoalRepository $goalRepository, BankGoalCalculator $goalCalculator, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('bank_goal_feed_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $goal = $goalRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$goal instanceof BankGoal) {
            throw $this->createNotFoundException();
        }

        $amount = (float) str_replace(',', '.', (string) $request->request->get('amount'));
        if ($goal->getLinkedAccount() !== null) {
            $this->addFlash('error', 'Cet objectif suit le solde d\'un compte: alimente le compte directement.');
        } elseif ($amount <= 0) {
            $this->addFlash('error', 'Le montant doit etre superieur a zero.');
        } else {
            $goal->addToCurrentAmount($amount);
            $goalCalculator->syncStatuses([$goal]);
            $entityManager->flush();
            $this->addFlash('success', sprintf('Objectif alimente de %s €.', number_format($amount, 2, ',', ' ')));
        }

        return $this->redirectToRoute('app_bank_goals');
    }

    #[Route('/goals/{publicId}/archive', name: 'goal_archive', methods: ['POST'])]
    public function goalArchive(string $publicId, Request $request, BankGoalRepository $goalRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('bank_goal_archive_' . $publicId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $goal = $goalRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$goal instanceof BankGoal) {
            throw $this->createNotFoundException();
        }

        $goal->setStatus(BankGoal::STATUS_ARCHIVED);
        $entityManager->flush();
        $this->addFlash('warning', 'Objectif archive.');

        return $this->redirectToRoute('app_bank_goals');
    }

    #[Route('/import', name: 'import', methods: ['GET', 'POST'])]
    public function import(Request $request, BankAccountRepository $accountRepository, BankAccountManager $accountManager, BankImportBatchRepository $batchRepository, BankImportService $importService, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BankImportType::class, null, ['account_choices' => $this->accountChoices($accountRepository, $accountManager)]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $profile = null;
            $mapping = [
                'date' => $data['dateColumn'],
                'label' => $data['labelColumn'],
                'amount' => $data['amountColumn'] ?: null,
                'debit' => $data['debitColumn'] ?: null,
                'credit' => $data['creditColumn'] ?: null,
            ];
            if (!empty($data['saveProfile'])) {
                $profile = (new BankImportProfile())
                    ->setUser($this->getUser())
                    ->setAccount($data['account'])
                    ->setName($data['profileName'] ?: 'Import ' . (new \DateTime())->format('d/m/Y'))
                    ->setDelimiter($data['delimiter'])
                    ->setDateFormat($data['dateFormat'])
                    ->setMapping($mapping);
                $entityManager->persist($profile);
            }
            $batch = $importService->import($this->getUser(), $data['account'], $data['file'], [
                'delimiter' => $data['delimiter'],
                'dateFormat' => $data['dateFormat'],
                'mapping' => $mapping,
            ], $profile);
            $entityManager->flush();
            $this->addFlash('success', sprintf('Import termine: %d ajoutee(s), %d ignoree(s), %d erreur(s).', $batch->getImportedCount(), $batch->getSkippedCount(), $batch->getErrorCount()));

            return $this->redirectToRoute('app_bank_import');
        }

        return $this->render('bank/import.html.twig', [
            'form' => $form,
            'batches' => $batchRepository->findRecentForUser($this->getUser()),
        ]);
    }

    #[Route('/export/{format}', name: 'export', methods: ['GET'])]
    public function export(string $format, Request $request, BankExportService $exportService): Response
    {
        $from = $this->dateFromQuery($request, 'from', new \DateTimeImmutable('first day of this month'));
        $to = $this->dateFromQuery($request, 'to', new \DateTimeImmutable('last day of this month'));

        return match ($format) {
            'csv' => $exportService->csv($this->getUser(), $from, $to),
            'xlsx' => $exportService->xlsx($this->getUser(), $from, $to),
            'pdf' => $this->downloadResponse(
                $exportService->pdf($this->getUser(), $from, $to),
                'application/pdf',
                sprintf('bank-%s-%s.pdf', $from->format('Ymd'), $to->format('Ymd')),
            ),
            default => throw $this->createNotFoundException(),
        };
    }

    #[Route('/transactions/{publicId}/attachments', name: 'attachment_add', methods: ['POST'])]
    public function attachmentAdd(string $publicId, Request $request, BankTransactionRepository $transactionRepository, BankAttachmentManager $attachmentManager, EntityManagerInterface $entityManager): Response
    {
        $transaction = $this->transactionOr404($publicId, $transactionRepository);
        $form = $this->createForm(BankAttachmentType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $attachmentManager->attach($this->getUser(), $transaction, $form->get('file')->getData());
            $entityManager->flush();
            $this->addFlash('success', 'Piece jointe ajoutee.');
        }

        return $this->redirectToRoute('app_bank_transaction_edit', ['publicId' => $publicId]);
    }

    #[Route('/attachments/{publicId}/download', name: 'attachment_download', methods: ['GET'])]
    public function attachmentDownload(string $publicId, BankAttachmentRepository $attachmentRepository, BankAttachmentManager $attachmentManager): Response
    {
        $attachment = $attachmentRepository->findOneForUserByPublicId($this->getUser(), $publicId);
        if ($attachment === null || $attachment->isArchived()) {
            throw $this->createNotFoundException();
        }

        return $this->downloadResponse($attachment->getContent(), $attachment->getMimeType(), $attachmentManager->originalName($attachment));
    }

    /** @return array<string, mixed> */
    private function accountTransactionListData(
        Request $request,
        BankAccount $account,
        ?\DateTimeImmutable $periodEnd,
        BankTransactionRepository $transactionRepository,
        BankCategoryRepository $categoryRepository,
    ): array {
        $realCriteria = $this->transactionListCriteria($request, 'real', $categoryRepository, 'desc');
        $plannedCriteria = $this->transactionListCriteria($request, 'planned', $categoryRepository, 'asc');
        $focusId = trim((string) $request->query->get('focus', ''));
        $focusTransaction = $focusId !== '' ? $transactionRepository->findOneForUserByPublicId($this->getUser(), $focusId) : null;
        $focusMessage = null;

        if ($focusTransaction !== null && $focusTransaction->getAccount()?->getPublicId() === $account->getPublicId() && $focusTransaction->isReal() && !$request->query->has('real_page')) {
            $targetPage = $transactionRepository->findRealPageNumberForTransaction($account, $focusTransaction, $realCriteria);
            if ($targetPage !== null) {
                $realCriteria = $realCriteria->withPage($targetPage);
            }
        }

        $realPage = $transactionRepository->findRealPageForAccount($account, $realCriteria);
        $realCriteria = $realCriteria->withPage($realPage->page);
        $plannedTransactions = $periodEnd !== null
            ? $transactionRepository->findPlannedForAccountUntilFiltered($account, $periodEnd, $plannedCriteria)
            : [];

        $focusVisible = false;
        if ($focusTransaction !== null && $focusTransaction->getAccount()?->getPublicId() === $account->getPublicId()) {
            $visibleTransactions = $focusTransaction->isReal() ? $realPage->items : $plannedTransactions;
            foreach ($visibleTransactions as $visibleTransaction) {
                if ($visibleTransaction->getPublicId() === $focusTransaction->getPublicId()) {
                    $focusVisible = true;
                    break;
                }
            }

            if (!$focusVisible) {
                if ($focusTransaction->getStatus() === BankTransaction::STATUS_PLANNED) {
                    $today = new \DateTimeImmutable('today');
                    if ($periodEnd === null) {
                        $focusMessage = 'L\'operation prevue a bien ete enregistree, mais aucune periode salariale n\'est actuellement disponible pour l\'afficher.';
                    } elseif ($focusTransaction->getDate() <= $today || $focusTransaction->getDate() > $periodEnd) {
                        $focusMessage = sprintf('L\'operation prevue a bien ete enregistree, mais elle est hors de la periode affichee, qui se termine le %s.', $periodEnd->format('d/m/Y'));
                    } else {
                        $focusMessage = 'L\'operation ciblee ne correspond plus aux filtres conserves de la liste des operations prevues.';
                    }
                } else {
                    $focusMessage = 'L\'operation ciblee ne correspond plus aux filtres conserves. Les filtres n\'ont pas ete modifies.';
                }
            }
        }

        $returnParameters = array_merge(
            $realCriteria->toQuery('real'),
            $plannedCriteria->toQuery('planned', false),
        );

        return [
            'categories' => $categoryRepository->findActiveForUser($this->getUser()),
            'transactionTypes' => $this->transactionTypeLabels(),
            'realCriteria' => $realCriteria,
            'realPage' => $realPage,
            'plannedCriteria' => $plannedCriteria,
            'plannedTransactions' => $plannedTransactions,
            'returnParameters' => $returnParameters,
            'focusId' => $focusVisible ? $focusId : null,
            'focusMessage' => $focusMessage,
        ];
    }

    private function transactionListCriteria(Request $request, string $prefix, BankCategoryRepository $categoryRepository, string $defaultDirection): BankTransactionListCriteria
    {
        $category = $this->categoryFromQuery($request, $prefix . '_category', $categoryRepository);
        if ($category?->isArchived()) {
            $category = null;
        }

        $allowedTypes = [BankTransaction::TYPE_INCOME, BankTransaction::TYPE_EXPENSE, BankTransaction::TYPE_TRANSFER_IN, BankTransaction::TYPE_TRANSFER_OUT];
        $type = (string) $request->query->get($prefix . '_type', '');
        $type = in_array($type, $allowedTypes, true) ? $type : null;
        $verification = (string) $request->query->get($prefix . '_verified', '');
        $verified = match ($verification) {
            'verified' => true,
            'unverified' => false,
            default => null,
        };
        $sort = (string) $request->query->get($prefix . '_sort', 'date');
        if (!in_array($sort, ['date', 'amount', 'category'], true)) {
            $sort = 'date';
        }
        $direction = strtolower((string) $request->query->get($prefix . '_direction', $defaultDirection));
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = $defaultDirection;
        }

        return new BankTransactionListCriteria(
            max(1, $request->query->getInt($prefix . '_page', 1)),
            $this->nullableDateFromQuery($request, $prefix . '_from'),
            $this->nullableDateFromQuery($request, $prefix . '_to'),
            $category,
            $type,
            $prefix === 'real' ? $verified : null,
            $sort,
            $direction,
            in_array($request->query->getInt($prefix . '_per_page', 25), [25, 50, 100], true) ? $request->query->getInt($prefix . '_per_page', 25) : 25,
        );
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, int|string>
     */
    private function sanitizeAccountListParameters(array $parameters): array
    {
        $allowed = [
            'real_page',
            'real_from',
            'real_to',
            'real_category',
            'real_type',
            'real_verified',
            'real_sort',
            'real_direction',
            'real_per_page',
            'planned_from',
            'planned_to',
            'planned_category',
            'planned_type',
            'planned_sort',
            'planned_direction',
            'planned_per_page',
        ];
        $sanitized = [];
        foreach ($allowed as $name) {
            if (!array_key_exists($name, $parameters) || !is_scalar($parameters[$name])) {
                continue;
            }
            $value = trim((string) $parameters[$name]);
            if ($value !== '') {
                $sanitized[$name] = $value;
            }
        }

        return $sanitized;
    }

    private function nullableDateFromQuery(Request $request, string $name): ?\DateTimeImmutable
    {
        $value = trim((string) $request->query->get($name, ''));
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) ? $date : null;
    }

    /** @return array<string, string> */
    private function transactionTypeLabels(): array
    {
        return [
            BankTransaction::TYPE_INCOME => 'Gain',
            BankTransaction::TYPE_EXPENSE => 'Depense',
            BankTransaction::TYPE_TRANSFER_IN => 'Virement entrant',
            BankTransaction::TYPE_TRANSFER_OUT => 'Virement sortant',
        ];
    }

    private function downloadResponse(string $content, string $mimeType, string $filename): Response
    {
        $response = new Response($content);
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Length', (string) strlen($content));
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename,
            $this->asciiFilenameFallback($filename),
        ));

        return $response;
    }

    private function asciiFilenameFallback(string $filename): string
    {
        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename);
        $fallback = trim((string) $fallback, '._-');

        return $fallback !== '' ? $fallback : 'telechargement';
    }

    private function accountOr404(string $publicId, BankAccountRepository $repository): BankAccount
    {
        $account = $repository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$account instanceof BankAccount) {
            throw $this->createNotFoundException();
        }

        return $account;
    }

    private function transactionOr404(string $publicId, BankTransactionRepository $repository): BankTransaction
    {
        $transaction = $repository->findOneForUserByPublicId($this->getUser(), $publicId);
        if (!$transaction instanceof BankTransaction) {
            throw $this->createNotFoundException();
        }

        return $transaction;
    }

    private function choices(BankAccountRepository $accountRepository, BankCategoryRepository $categoryRepository, BankAccountManager $accountManager): array
    {
        return [
            'account_choices' => $this->accountChoices($accountRepository, $accountManager),
            'category_choices' => $this->categoryChoices($categoryRepository),
        ];
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

    private function debitAccountChoices(BankAccountRepository $accountRepository, BankAccountManager $accountManager, ?BankAccount $exclude = null): array
    {
        $choices = [];
        foreach ($accountRepository->findActiveForUser($this->getUser()) as $account) {
            if ($account->getType() !== BankAccount::TYPE_CURRENT) {
                continue;
            }
            if ($exclude !== null && $account->getPublicId() === $exclude->getPublicId()) {
                continue;
            }
            $label = $accountManager->displayName($account);
            $choices[$label !== '' ? $label : 'Compte ' . substr($account->getPublicId(), -6)] = $account;
        }

        return $choices;
    }

    private function goalAccountChoices(BankAccountRepository $accountRepository, BankAccountManager $accountManager, ?BankAccount $currentlyLinked = null): array
    {
        $choices = [];
        $accounts = $accountRepository->findActiveForUser($this->getUser());
        if ($currentlyLinked !== null && !in_array($currentlyLinked, $accounts, true)) {
            $accounts[] = $currentlyLinked;
        }

        foreach ($accounts as $account) {
            if ($account->isLoan()) {
                continue;
            }
            $label = $accountManager->displayName($account);
            if ($account->isArchived()) {
                $label .= ' (archive)';
            }
            $choices[$label !== '' ? $label : 'Compte ' . substr($account->getPublicId(), -6)] = $account;
        }

        return $choices;
    }

    private function categoryChoices(BankCategoryRepository $categoryRepository): array
    {
        $choices = [];
        foreach ($categoryRepository->findActiveForUser($this->getUser()) as $category) {
            $choices[$category->getName()] = $category;
        }

        return $choices;
    }

    private function expenseCategoryChoices(BankCategoryRepository $categoryRepository): array
    {
        $choices = [];
        foreach ($categoryRepository->findActiveForUser($this->getUser()) as $category) {
            if ($category->getType() !== BankCategory::TYPE_EXPENSE) {
                continue;
            }
            $choices[$category->getName()] = $category;
        }

        return $choices;
    }

    private function accountFromQuery(Request $request, string $name, BankAccountRepository $repository): ?BankAccount
    {
        $value = (string) $request->query->get($name, '');

        return $value !== '' ? $repository->findOneForUserByPublicId($this->getUser(), $value) : null;
    }

    private function categoryFromQuery(Request $request, string $name, BankCategoryRepository $repository): ?BankCategory
    {
        $value = (string) $request->query->get($name, '');

        return $value !== '' ? $repository->findOneForUserByPublicId($this->getUser(), $value) : null;
    }

    private function dateFromQuery(Request $request, string $name, \DateTimeImmutable $default): \DateTimeImmutable
    {
        $value = (string) $request->query->get($name, '');
        if ($value === '') {
            return $default;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date ?: $default;
    }
}
