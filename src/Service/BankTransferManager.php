<?php

namespace App\Service;

use App\Entity\BankAccount;
use App\Entity\BankCategory;
use App\Entity\BankTransaction;
use App\Entity\User;
use App\Service\UlidGenerator;
use Doctrine\ORM\EntityManagerInterface;

final class BankTransferManager
{
    public function __construct(
        private readonly BankEncryptionService $encryption,
        private readonly EntityManagerInterface $entityManager,
        private readonly BankAuditLogger $auditLogger,
    ) {
    }

    public function create(User $user, BankAccount $source, BankAccount $target, float $amount, \DateTimeInterface $date, string $label, ?BankCategory $category = null, string $status = BankTransaction::STATUS_REAL): array
    {
        if ($source->getPublicId() === $target->getPublicId()) {
            throw new \InvalidArgumentException('Les comptes source et cible doivent etre differents.');
        }

        $group = UlidGenerator::generate();
        $encryptedLabel = $this->encryption->encrypt($label) ?? '';

        $out = (new BankTransaction())
            ->setUser($user)
            ->setAccount($source)
            ->setCategory($category)
            ->setDate($date)
            ->setEncryptedLabel($encryptedLabel)
            ->setSignedAmount($amount, BankTransaction::TYPE_TRANSFER_OUT)
            ->setStatus($status)
            ->setSource(BankTransaction::SOURCE_TRANSFER)
            ->setTransferGroup($group);

        $in = (new BankTransaction())
            ->setUser($user)
            ->setAccount($target)
            ->setCategory($category)
            ->setDate($date)
            ->setEncryptedLabel($encryptedLabel)
            ->setSignedAmount($amount, BankTransaction::TYPE_TRANSFER_IN)
            ->setStatus($status)
            ->setSource(BankTransaction::SOURCE_TRANSFER)
            ->setTransferGroup($group);

        $out->setMirrorTransaction($in);
        $in->setMirrorTransaction($out);

        $this->entityManager->persist($out);
        $this->entityManager->persist($in);
        $this->auditLogger->record($user, 'bank.transfer.create', 'bank_transfer', $group, [
            'amount' => $amount,
            'source' => $source->getPublicId(),
            'target' => $target->getPublicId(),
        ]);

        return [$out, $in];
    }

    public function updatePair(User $user, BankTransaction $anchor, BankAccount $source, BankAccount $target, float $amount, \DateTimeInterface $date, string $label, ?BankCategory $category = null, string $status = BankTransaction::STATUS_REAL): array
    {
        if ($source->getPublicId() === $target->getPublicId()) {
            throw new \InvalidArgumentException('Les comptes source et cible doivent etre differents.');
        }

        $mirror = $anchor->getMirrorTransaction();
        if (!$mirror instanceof BankTransaction) {
            throw new \InvalidArgumentException('Ce virement interne n\'a pas d operation miroir.');
        }

        $out = $anchor->getType() === BankTransaction::TYPE_TRANSFER_OUT ? $anchor : $mirror;
        $in = $anchor->getType() === BankTransaction::TYPE_TRANSFER_IN ? $anchor : $mirror;
        $group = $out->getTransferGroup() ?? $in->getTransferGroup() ?? UlidGenerator::generate();
        $encryptedLabel = $this->encryption->encrypt($label) ?? '';

        $out
            ->setUser($user)
            ->setAccount($source)
            ->setCategory($category)
            ->setDate($date)
            ->setEncryptedLabel($encryptedLabel)
            ->setSignedAmount($amount, BankTransaction::TYPE_TRANSFER_OUT)
            ->setStatus($status)
            ->setSource(BankTransaction::SOURCE_TRANSFER)
            ->setTransferGroup($group)
            ->setMirrorTransaction($in);

        $in
            ->setUser($user)
            ->setAccount($target)
            ->setCategory($category)
            ->setDate($date)
            ->setEncryptedLabel($encryptedLabel)
            ->setSignedAmount($amount, BankTransaction::TYPE_TRANSFER_IN)
            ->setStatus($status)
            ->setSource(BankTransaction::SOURCE_TRANSFER)
            ->setTransferGroup($group)
            ->setMirrorTransaction($out);

        if ($status === BankTransaction::STATUS_PLANNED) {
            $out->markUnverified();
            $in->markUnverified();
        }

        $this->auditLogger->record($user, 'bank.transfer.update', 'bank_transfer', $group, [
            'amount' => $amount,
            'source' => $source->getPublicId(),
            'target' => $target->getPublicId(),
            'status' => $status,
        ]);

        return [$out, $in];
    }
}
