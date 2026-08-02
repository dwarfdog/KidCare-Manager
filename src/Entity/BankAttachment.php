<?php

namespace App\Entity;

use App\Repository\BankAttachmentRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BankAttachmentRepository::class)]
#[ORM\Index(columns: ['user_id', 'transaction_id'], name: 'IDX_BANK_ATTACHMENT_USER_TRANSACTION')]
class BankAttachment
{
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
    private ?BankTransaction $transaction = null;

    #[ORM\Column(length: 1024)]
    private string $encryptedOriginalName = '';

    #[ORM\Column(length: 120)]
    private string $mimeType = '';

    #[ORM\Column]
    private int $sizeBytes = 0;

    #[ORM\Column(type: Types::BLOB)]
    private mixed $content = null;

    #[ORM\Column(length: 64)]
    private string $contentHash = '';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $archivedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->publicId = UlidGenerator::generate();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): string
    {
        return $this->publicId;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getTransaction(): ?BankTransaction
    {
        return $this->transaction;
    }

    public function setTransaction(?BankTransaction $transaction): static
    {
        $this->transaction = $transaction;

        return $this;
    }

    public function getEncryptedOriginalName(): string
    {
        return $this->encryptedOriginalName;
    }

    public function setEncryptedOriginalName(string $encryptedOriginalName): static
    {
        $this->encryptedOriginalName = $encryptedOriginalName;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(int $sizeBytes): static
    {
        $this->sizeBytes = max(0, $sizeBytes);

        return $this;
    }

    public function getContent(): string
    {
        if (is_resource($this->content)) {
            return stream_get_contents($this->content) ?: '';
        }

        return (string) $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        $this->contentHash = hash('sha256', $content);
        $this->sizeBytes = strlen($content);

        return $this;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    public function getArchivedAt(): ?\DateTimeInterface
    {
        return $this->archivedAt;
    }

    public function archive(): static
    {
        $this->archivedAt = new \DateTime();

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
