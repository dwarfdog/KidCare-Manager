<?php

namespace App\Service;

use App\Entity\Nanny;
use App\Entity\NannyDocument;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class NannyDocumentManager
{
    private const MAX_SIZE = 10 * 1024 * 1024;

    public function __construct(private readonly DocumentEncryptionService $encryption, private readonly EntityManagerInterface $entityManager, private readonly ActivityLogger $activityLogger) {}

    public function add(User $user, Nanny $nanny, UploadedFile $file, string $type, ?\DateTimeInterface $expiresAt): NannyDocument
    {
        if ($file->getSize() === false || $file->getSize() > self::MAX_SIZE) {
            throw new \InvalidArgumentException('Le document PDF ne doit pas dépasser 10 Mo.');
        }
        if (($file->getMimeType() ?? '') !== 'application/pdf') {
            throw new \InvalidArgumentException('Seuls les documents PDF sont acceptés.');
        }
        $content = (string) file_get_contents($file->getPathname());
        if (!str_starts_with($content, '%PDF-')) {
            throw new \InvalidArgumentException('Le fichier transmis n\'est pas un PDF valide.');
        }

        $document = (new NannyDocument())->setUser($user)->setNanny($nanny)->setType($type)
            ->setEncryptedOriginalName($this->encryption->encrypt($file->getClientOriginalName()))->setMimeType('application/pdf')
            ->setSizeBytes(strlen($content))->setContentHash(hash('sha256', $content))->setEncryptedContent($this->encryption->encrypt($content))->setExpiresAt($expiresAt);
        $this->entityManager->persist($document);
        $this->activityLogger->record($user, 'nanny.document.add', 'Document privé ajouté.', ['nanny' => $nanny->getPublicId(), 'type' => $type]);
        return $document;
    }

    public function name(NannyDocument $document): string
    {
        return $this->encryption->decrypt($document->getEncryptedOriginalName());
    }
    public function content(NannyDocument $document): string
    {
        return $this->encryption->decrypt($document->getEncryptedContent());
    }
    public function archive(User $user, NannyDocument $document): void
    {
        $document->archive();
        $this->activityLogger->record($user, 'nanny.document.archive', 'Document privé archivé.', ['document' => $document->getPublicId()]);
    }
}
