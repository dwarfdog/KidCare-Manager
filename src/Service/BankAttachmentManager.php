<?php

namespace App\Service;

use App\Entity\BankAttachment;
use App\Entity\BankTransaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class BankAttachmentManager
{
    private const MAX_SIZE = 20 * 1024 * 1024;
    private const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function __construct(
        private readonly BankEncryptionService $encryption,
        private readonly EntityManagerInterface $entityManager,
        private readonly BankAuditLogger $auditLogger,
    ) {
    }

    public function attach(User $user, BankTransaction $transaction, UploadedFile $file): BankAttachment
    {
        if ($file->getSize() === null || $file->getSize() > self::MAX_SIZE) {
            throw new \InvalidArgumentException('La piece jointe ne doit pas depasser 20 Mo.');
        }

        $mimeType = $file->getMimeType() ?? '';
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('Seuls les fichiers PDF et images sont acceptes.');
        }

        $attachment = (new BankAttachment())
            ->setUser($user)
            ->setTransaction($transaction)
            ->setEncryptedOriginalName($this->encryption->encrypt($file->getClientOriginalName()) ?? '')
            ->setMimeType($mimeType)
            ->setContent((string) file_get_contents($file->getPathname()));

        $this->entityManager->persist($attachment);
        $this->auditLogger->record($user, 'bank.attachment.create', 'bank_attachment', $attachment->getPublicId(), [
            'transaction' => $transaction->getPublicId(),
            'mime' => $mimeType,
            'size' => $attachment->getSizeBytes(),
        ]);

        return $attachment;
    }

    public function archive(User $user, BankAttachment $attachment): void
    {
        $attachment->archive();
        $this->auditLogger->record($user, 'bank.attachment.archive', 'bank_attachment', $attachment->getPublicId());
    }

    public function originalName(BankAttachment $attachment): string
    {
        return $this->encryption->decrypt($attachment->getEncryptedOriginalName());
    }
}
