<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\BankLoanScheduleGenerator;
use App\Service\BankRecurringTransactionGenerator;
use App\Service\BankTransactionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:bank:generate', description: 'Genere les operations bancaires recurrentes et realise les operations dues.')]
final class BankGenerateCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly BankRecurringTransactionGenerator $generator,
        private readonly BankLoanScheduleGenerator $loanScheduleGenerator,
        private readonly BankTransactionManager $transactionManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $created = 0;
        $realized = 0;

        $cleaned = 0;
        foreach ($this->userRepository->findAll() as $user) {
            $created += $this->generator->generateForUser($user);
            $created += $this->loanScheduleGenerator->generateForUser($user);
            $cleaned += $this->transactionManager->archiveOrphanedPlanned($user);
            $realized += $this->transactionManager->realizeDuePlanned($user);
        }

        $this->entityManager->flush();
        $output->writeln(sprintf('Bank: %d generee(s), %d realisee(s), %d prevue(s) orpheline(s) archivee(s).', $created, $realized, $cleaned));

        return Command::SUCCESS;
    }
}
