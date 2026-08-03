<?php

namespace App\Service;

use App\Entity\Nanny;
use App\Entity\User;
use App\Repository\NannyAssignmentRepository;
use App\Repository\NannyRepository;
use Doctrine\ORM\EntityManagerInterface;

final class UserAccountManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NannyAssignmentRepository $assignmentRepository,
        private readonly NannyRepository $nannyRepository,
        private readonly ActivityLogger $activityLogger,
        private readonly NannyOwnershipService $ownershipService,
    ) {}

    public function deleteAccount(User $user): void
    {
        $username = $user->getUserIdentifier();
        $candidateNannies = [];

        foreach ($user->getNannyAssignments() as $assignment) {
            if ($assignment->getNanny() instanceof Nanny) {
                $candidateNannies[$assignment->getNanny()->getPublicId()] = $assignment->getNanny();
            }
            $this->entityManager->remove($assignment);
        }

        foreach ($user->getCareTemplates() as $template) {
            $this->entityManager->remove($template);
        }

        foreach ($user->getCares() as $care) {
            $this->entityManager->remove($care);
        }

        foreach ($user->getMonthlyPayments() as $payment) {
            $this->entityManager->remove($payment);
        }

        foreach ($user->getNannyRates() as $rate) {
            $this->entityManager->remove($rate);
        }

        foreach ($this->nannyRepository->findBy(['createdBy' => $user]) as $nanny) {
            if (!$this->ownershipService->transferBeforeAccountDeletion($nanny, $user)) {
                $nanny->setCreatedBy(null);
            }
            $candidateNannies[$nanny->getPublicId()] = $nanny;
        }

        $this->activityLogger->record($user, 'account.delete', 'Compte utilisateur supprime.', ['username' => $username]);
        $this->entityManager->flush();

        foreach ($candidateNannies as $nanny) {
            if ($this->assignmentRepository->countActiveOrHistoricalForNanny($nanny) === 0) {
                $this->entityManager->remove($nanny);
            }
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}
