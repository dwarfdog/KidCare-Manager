<?php

namespace App\Service;

use App\Entity\BankAccount;
use App\Entity\BankCategory;
use App\Entity\BankTransaction;
use App\Entity\MonthlyPayment;
use App\Entity\User;
use App\Repository\BankCategoryRepository;
use App\Repository\BankTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class KidCareBankBridge
{
    public function __construct(
        private readonly BankEncryptionService $encryption,
        private readonly BankCategoryRepository $categoryRepository,
        private readonly BankTransactionRepository $transactionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly BankAuditLogger $auditLogger,
    ) {
    }

    public function hasLinkedTransaction(User $user, MonthlyPayment $payment): bool
    {
        return $this->transactionRepository->findOneForMonthlyPayment($user, $payment) !== null;
    }

    public function createExpense(User $user, MonthlyPayment $payment, BankAccount $account, ?BankCategory $category, \DateTimeInterface $date, ?string $label = null): BankTransaction
    {
        if ($this->hasLinkedTransaction($user, $payment)) {
            throw new \LogicException('Ce paiement KidCare est deja reporte dans Bank Manager.');
        }

        $category ??= $this->categoryRepository->findOneByName($user, 'Nounou');
        $label ??= sprintf('KidCare - %s - %s', $payment->getNannyDisplayName(), $payment->getMonth());

        $transaction = (new BankTransaction())
            ->setUser($user)
            ->setAccount($account)
            ->setCategory($category)
            ->setDate($date)
            ->setEncryptedLabel($this->encryption->encrypt($label) ?? '')
            ->setSignedAmount($payment->getTotalAmount(), BankTransaction::TYPE_EXPENSE)
            ->setStatus(BankTransaction::STATUS_REAL)
            ->setSource(BankTransaction::SOURCE_KIDCARE)
            ->setMonthlyPayment($payment);

        $this->entityManager->persist($transaction);
        $this->auditLogger->record($user, 'bank.kidcare.create_payment_transaction', 'bank_transaction', $transaction->getPublicId(), [
            'payment' => $payment->getPublicId(),
            'amount' => $payment->getTotalAmount(),
        ]);

        return $transaction;
    }

    public function deleteLinkedTransaction(User $user, MonthlyPayment $payment): bool
    {
        $transaction = $this->transactionRepository->findOneForMonthlyPayment($user, $payment);
        if ($transaction === null) {
            return false;
        }

        $publicId = $transaction->getPublicId();
        $this->entityManager->remove($transaction);
        $this->auditLogger->record($user, 'bank.kidcare.delete_payment_transaction', 'bank_transaction', $publicId, [
            'payment' => $payment->getPublicId(),
        ]);

        return true;
    }
}
