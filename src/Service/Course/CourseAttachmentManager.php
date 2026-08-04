<?php

namespace App\Service\Course;

use App\Entity\CourseAttachment;
use App\Entity\CourseReceipt;
use App\Entity\User;
use App\Service\BankEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CourseAttachmentManager
{
    private const MAX_SIZE = 20 * 1024 * 1024;
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp', 'image/tiff',
        'text/plain', 'text/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function __construct(
        private readonly BankEncryptionService $encryption,
        private readonly EntityManagerInterface $entityManager,
        private readonly CourseAuditLogger $auditLogger,
    ) {
    }

    public function attach(User $user, CourseReceipt $receipt, UploadedFile $file): CourseAttachment
    {
        if ($file->getSize() === null || $file->getSize() > self::MAX_SIZE) {
            throw new \InvalidArgumentException('Le fichier ne doit pas depasser 20 Mo.');
        }

        $mimeType = $this->resolveMimeType($file);
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('Format non supporte. Formats acceptes : photo (JPEG/PNG/WEBP), PDF, TXT, DOCX, CSV, XLSX.');
        }

        $attachment = (new CourseAttachment())
            ->setUser($user)
            ->setReceipt($receipt)
            ->setEncryptedOriginalName($this->encryption->encrypt($file->getClientOriginalName()) ?? '')
            ->setMimeType($mimeType)
            ->setContent((string) file_get_contents($file->getPathname()));

        $this->entityManager->persist($attachment);
        $this->auditLogger->record($user, 'courses.attachment.create', 'course_attachment', $attachment->getPublicId(), [
            'receipt' => $receipt->getPublicId(),
            'mime' => $mimeType,
            'size' => $attachment->getSizeBytes(),
        ]);

        return $attachment;
    }

    public function archive(User $user, CourseAttachment $attachment): void
    {
        $attachment->archive();
        $this->auditLogger->record($user, 'courses.attachment.archive', 'course_attachment', $attachment->getPublicId());
    }

    public function originalName(CourseAttachment $attachment): string
    {
        return $this->encryption->decrypt($attachment->getEncryptedOriginalName());
    }

    /**
     * Certains navigateurs/OS n'envoient pas toujours un mime-type fiable pour .docx/.csv ;
     * on retombe sur l'extension du nom de fichier dans ce cas.
     */
    private function resolveMimeType(UploadedFile $file): string
    {
        $mimeType = $file->getMimeType() ?? '';
        if ($mimeType !== '' && $mimeType !== 'application/octet-stream' && $mimeType !== 'text/plain') {
            return $mimeType;
        }

        $extension = strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'csv' => 'text/csv',
            'txt' => 'text/plain',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            default => $mimeType,
        };
    }
}
