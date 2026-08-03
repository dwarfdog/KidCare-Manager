<?php

namespace App\Entity;

use App\Repository\NannyDocumentRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NannyDocumentRepository::class)]
#[ORM\Index(columns: ['user_id', 'nanny_id', 'archived_at'], name: 'IDX_NANNY_DOCUMENT_OWNER')]
class NannyDocument
{
    public const TYPES = ['identity', 'contract', 'certificate', 'sick_leave', 'medical_nanny', 'medical_child'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 26, unique: true)]
    private string $publicId;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Nanny $nanny = null;
    #[ORM\Column(length: 32)]
    private string $type = 'contract';
    #[ORM\Column(length: 2048)]
    private string $encryptedOriginalName = '';
    #[ORM\Column(length: 100)]
    private string $mimeType = 'application/pdf';
    #[ORM\Column]
    private int $sizeBytes = 0;
    #[ORM\Column(type: Types::TEXT)]
    private string $encryptedContent = '';
    #[ORM\Column(length: 64)]
    private string $contentHash = '';
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expiresAt = null;
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $archivedAt = null;
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct() { $this->publicId = UlidGenerator::generate(); $this->createdAt = new \DateTime(); }
    public function getId(): ?int { return $this->id; }
    public function getPublicId(): string { return $this->publicId; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getNanny(): ?Nanny { return $this->nanny; }
    public function setNanny(?Nanny $nanny): static { $this->nanny = $nanny; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): static { if (!in_array($type, self::TYPES, true)) { throw new \InvalidArgumentException('Type de document invalide.'); } $this->type = $type; return $this; }
    public function getEncryptedOriginalName(): string { return $this->encryptedOriginalName; }
    public function setEncryptedOriginalName(string $value): static { $this->encryptedOriginalName = $value; return $this; }
    public function getMimeType(): string { return $this->mimeType; }
    public function setMimeType(string $value): static { $this->mimeType = $value; return $this; }
    public function getSizeBytes(): int { return $this->sizeBytes; }
    public function setSizeBytes(int $value): static { $this->sizeBytes = max(0, $value); return $this; }
    public function getEncryptedContent(): string { return $this->encryptedContent; }
    public function setEncryptedContent(string $value): static { $this->encryptedContent = $value; return $this; }
    public function getContentHash(): string { return $this->contentHash; }
    public function setContentHash(string $value): static { $this->contentHash = $value; return $this; }
    public function getExpiresAt(): ?\DateTimeInterface { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeInterface $value): static { $this->expiresAt = $value; return $this; }
    public function getArchivedAt(): ?\DateTimeInterface { return $this->archivedAt; }
    public function archive(): static { $this->archivedAt = new \DateTime(); return $this; }
    public function isArchived(): bool { return $this->archivedAt !== null; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
