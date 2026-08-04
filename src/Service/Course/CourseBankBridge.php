<?php

namespace App\Service\Course;

use App\Entity\BankAccount;
use App\Entity\BankCategory;
use App\Entity\BankTransaction;
use App\Entity\CourseReceipt;
use App\Entity\CourseStore;
use App\Entity\User;
use App\Service\BankEncryptionService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cree la App\Entity\BankTransaction agregee correspondant a un ticket de courses confirme.
 * Clone du meme role que App\Service\KidCareBankBridge pour les paiements KidCare.
 */
final class CourseBankBridge
{
    public function __construct(
        private readonly BankEncryptionService $encryption,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function hasLinkedTransaction(CourseReceipt $receipt): bool
    {
        return $receipt->getBankTransaction() !== null;
    }

    public function createExpense(User $user, CourseReceipt $receipt, BankAccount $account, BankCategory $category, ?CourseStore $store): BankTransaction
    {
        if ($this->hasLinkedTransaction($receipt)) {
            throw new \LogicException('Ce ticket est deja reporte dans Bank Manager.');
        }

        $storeLabel = $store !== null ? $this->encryption->decrypt($store->getEncryptedName()) : null;
        $label = sprintf('Courses - %s', $storeLabel !== null && $storeLabel !== '' ? $storeLabel : ($receipt->getPurchaseDate()?->format('d/m/Y') ?? 'Ticket'));

        $transaction = (new BankTransaction())
            ->setUser($user)
            ->setAccount($account)
            ->setCategory($category)
            ->setDate($receipt->getPurchaseDate() ?? new \DateTime('today'))
            ->setEncryptedLabel($this->encryption->encrypt($label) ?? '')
            ->setSignedAmount($receipt->getTotalAmount(), BankTransaction::TYPE_EXPENSE)
            ->setStatus(BankTransaction::STATUS_REAL)
            ->setSource(BankTransaction::SOURCE_COURSES);

        $this->entityManager->persist($transaction);
        $receipt->setBankTransaction($transaction);

        return $transaction;
    }
}
