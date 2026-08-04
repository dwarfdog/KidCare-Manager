<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\BankGoalRepository;
use App\Service\Course\CourseCategorySeeder;
use Doctrine\ORM\EntityManagerInterface;

final class BankDashboardSynchronizer
{
    public function __construct(
        private readonly BankCategorySeeder $categorySeeder,
        private readonly CourseCategorySeeder $courseCategorySeeder,
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
        // Les categories Courses par defaut sont rattachees a la categorie Bank "Alimentation" :
        // doit toujours s'executer apres le seeding des categories Bank ci-dessus.
        $this->courseCategorySeeder->ensureForUser($user);
        $this->recurringGenerator->generateForUser($user);
        $this->loanScheduleGenerator->generateForUser($user);
        $this->transactionManager->archiveOrphanedPlanned($user);
        $this->transactionManager->realizeDuePlanned($user);
        $this->goalCalculator->syncStatuses($this->goalRepository->findActiveForUser($user));
        $this->entityManager->flush();
    }
}
