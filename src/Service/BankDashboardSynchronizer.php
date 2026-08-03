<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\BankGoalRepository;
use Doctrine\ORM\EntityManagerInterface;

final class BankDashboardSynchronizer
{
    public function __construct(
        private readonly BankCategorySeeder $categorySeeder,
        private readonly BankRecurringTransactionGenerator $recurringGenerator,
        private readonly BankLoanScheduleGenerator $loanScheduleGenerator,
        private readonly BankTransactionManager $transactionManager,
        private readonly BankGoalRepository $goalRepository,
        private readonly BankGoalCalculator $goalCalculator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function synchronize(User $user): void
    {
        $this->categorySeeder->ensureForUser($user);
        $this->recurringGenerator->generateForUser($user);
        $this->loanScheduleGenerator->generateForUser($user);
        $this->transactionManager->archiveOrphanedPlanned($user);
        $this->transactionManager->realizeDuePlanned($user);
        $this->goalCalculator->syncStatuses($this->goalRepository->findActiveForUser($user));
        $this->entityManager->flush();
    }
}
