<?php

namespace App\Service;

use App\Entity\BankRecurringException;
use App\Entity\BankRecurringRule;
use App\Entity\BankTransaction;
use App\Entity\User;
use App\Repository\BankRecurringExceptionRepository;
use App\Repository\BankRecurringRuleRepository;
use App\Repository\BankTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class BankRecurringTransactionGenerator
{
    public function __construct(
        private readonly BankRecurringRuleRepository $ruleRepository,
        private readonly BankRecurringExceptionRepository $exceptionRepository,
        private readonly BankTransactionRepository $transactionRepository,
        private readonly BankEncryptionService $encryption,
        private readonly BankRecurringSchedule $schedule,
        private readonly EntityManagerInterface $entityManager,
        private readonly BankAuditLogger $auditLogger,
    ) {
    }

    public function generateForUser(User $user, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): int
    {
        $from = $from !== null ? \DateTimeImmutable::createFromInterface($from) : new \DateTimeImmutable('today');
        $to = $to !== null ? \DateTimeImmutable::createFromInterface($to) : $from->modify('+3 months');
        $created = 0;

        foreach ($this->ruleRepository->findActiveForUser($user) as $rule) {
            $created += $this->generateForRule($user, $rule, $from, $to);
        }

        return $created;
    }

    public function generateForRule(User $user, BankRecurringRule $rule, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): int
    {
        if (!$rule->isActive()) {
            return 0;
        }

        $from = $from !== null ? \DateTimeImmutable::createFromInterface($from) : new \DateTimeImmutable('today');
        $to = $to !== null ? \DateTimeImmutable::createFromInterface($to) : $from->modify('+3 months');
        $exceptions = $this->exceptionRepository->findIndexedForRule($rule);
        $cursor = \DateTimeImmutable::createFromInterface($rule->getStartDate());
        $created = 0;

        while ($cursor < $from) {
            $cursor = $this->schedule->nextOccurrence($rule, $cursor);
        }

        while ($cursor <= $to && $rule->isActive()) {
            if ($rule->getEndDate() !== null && $cursor > \DateTimeImmutable::createFromInterface($rule->getEndDate())) {
                break;
            }

            $dateKey = $cursor->format('Y-m-d');
            $exception = $exceptions[$dateKey] ?? null;
            if ($exception?->getAction() !== BankRecurringException::ACTION_SKIP && $this->transactionRepository->findOneByRuleAndDate($user, $rule, $cursor) === null) {
                $transactionDate = $exception?->getOverrideDate() !== null ? \DateTimeImmutable::createFromInterface($exception->getOverrideDate()) : $cursor;
                $amount = $exception?->getOverrideAmount() ?? $rule->getAmount();
                $label = $exception?->getOverrideEncryptedLabel() !== null
                    ? $this->encryption->decrypt($exception->getOverrideEncryptedLabel())
                    : $this->encryption->decrypt($rule->getEncryptedLabel());

                $transaction = (new BankTransaction())
                    ->setUser($user)
                    ->setAccount($rule->getAccount())
                    ->setCategory($rule->getCategory())
                    ->setDate($transactionDate)
                    ->setEncryptedLabel($this->encryption->encrypt($label) ?? '')
                    ->setSignedAmount($amount)
                    ->setStatus($transactionDate <= new \DateTimeImmutable('today') ? BankTransaction::STATUS_REAL : BankTransaction::STATUS_PLANNED)
                    ->setSource(BankTransaction::SOURCE_RECURRING)
                    ->setRecurringRule($rule);

                if ($transaction->isReal() && $rule->getLoanRemainingCapital() !== null) {
                    $rule->decreaseLoanRemainingCapital($transaction->getAbsoluteAmount());
                }

                $this->entityManager->persist($transaction);
                $this->auditLogger->record($user, 'bank.recurring.generate_transaction', 'bank_transaction', $transaction->getPublicId(), [
                    'rule' => $rule->getPublicId(),
                    'date' => $transactionDate->format('Y-m-d'),
                ]);
                ++$created;
            }

            $cursor = $this->schedule->nextOccurrence($rule, $cursor);
        }

        return $created;
    }
}
