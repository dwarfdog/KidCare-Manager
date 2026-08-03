<?php

namespace App\Controller;

use App\Repository\BankAccountRepository;
use App\Repository\BankAssetRepository;
use App\Repository\BankAttachmentRepository;
use App\Repository\BankBudgetRepository;
use App\Repository\BankCategoryRepository;
use App\Repository\BankCategoryRuleRepository;
use App\Repository\BankGoalRepository;
use App\Repository\BankRecurringRuleRepository;
use App\Repository\BankSimulationRepository;
use App\Repository\BankTransactionRepository;
use App\Repository\CareAbsenceRepository;
use App\Repository\CareRepository;
use App\Repository\CareTemplateRepository;
use App\Repository\NannyAssignmentRepository;
use App\Repository\NannyDocumentRepository;
use App\Repository\NannyRateRepository;
use App\Service\BankAccountManager;
use App\Service\BankAttachmentManager;
use App\Service\BankEncryptionService;
use App\Service\BankTransactionManager;
use App\Service\NannyDocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')] final class ArchiveController extends AbstractController
{
    #[Route('/archives', name: 'app_archives', methods: ['GET'])] public function index(BankAccountRepository $accounts, BankTransactionRepository $transactions, BankRecurringRuleRepository $recurring, BankCategoryRepository $categories, BankBudgetRepository $budgets, BankGoalRepository $goals, BankAttachmentRepository $attachments, BankCategoryRuleRepository $rules, BankSimulationRepository $simulations, BankAssetRepository $assets, CareRepository $cares, CareAbsenceRepository $absences, CareTemplateRepository $templates, NannyRateRepository $rates, NannyDocumentRepository $documents, NannyAssignmentRepository $assignments, BankAccountManager $accountManager, BankTransactionManager $transactionManager, BankAttachmentManager $attachmentManager, NannyDocumentManager $documentManager, BankEncryptionService $encryption): Response
    {
        return $this->render('archives/index.html.twig', ['groups' => [
            'Comptes bancaires' => $accounts->findArchivedForUser($this->getUser()),
            'Opérations bancaires' => $transactions->findArchivedForUser($this->getUser()),
            'Récurrences' => $recurring->findFilteredForUser($this->getUser(), ['active' => false]),
            'Catégories' => $categories->findArchivedForUser($this->getUser()),
            'Budgets' => $budgets->findArchivedForUser($this->getUser()),
            'Objectifs' => $goals->findArchivedForUser($this->getUser()),
            'Pièces jointes bancaires' => $attachments->findArchivedForUser($this->getUser()),
            'Règles de catégorisation' => $rules->findArchivedForUser($this->getUser()),
            'Simulations' => $simulations->findArchivedForUser($this->getUser()),
            'Actifs et dettes' => $assets->findArchivedForUser($this->getUser()),
            'Nounous détachées' => $assignments->findDetachedForUser($this->getUser()),
            'Gardes' => $cares->findArchivedForUser($this->getUser()),
            'Indisponibilités' => $absences->findArchivedForUser($this->getUser()),
            'Templates de garde' => $templates->findArchivedForUser($this->getUser()),
            'Tarifs nounou' => $rates->findArchivedForUser($this->getUser()),
            'Documents privés' => $documents->findArchivedForUser($this->getUser())
        ], 'accountManager' => $accountManager, 'transactionManager' => $transactionManager, 'attachmentManager' => $attachmentManager, 'documentManager' => $documentManager, 'encryption' => $encryption]);
    }
}
