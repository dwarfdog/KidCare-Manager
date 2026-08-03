<?php

namespace App\Entity;

use App\Repository\BankImportBatchRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BankImportBatchRepository::class)]
#[ORM\Index(columns: ['user_id', 'created_at'], name: 'IDX_BANK_IMPORT_BATCH_USER_DATE')]
class BankImportBatch
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
    private ?BankAccount $account = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?BankImportProfile $profile = null;

    #[ORM\Column(length: 255)]
    private string $originalFilename = '';

    #[ORM\Column(options: ['default' => 0])]
    private int $importedCount = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $skippedCount = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $errorCount = 0;

    #[ORM\Column(type: Types::JSON)]
    private array $errors = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $undoneAt = null;

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

    public function getAccount(): ?BankAccount
    {
        return $this->account;
    }

    public function setAccount(?BankAccount $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function getProfile(): ?BankImportProfile
    {
        return $this->profile;
    }

    public function setProfile(?BankImportProfile $profile): static
    {
        $this->profile = $profile;

        return $this;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function setImportedCount(int $importedCount): static
    {
        $this->importedCount = max(0, $importedCount);

        return $this;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function setSkippedCount(int $skippedCount): static
    {
        $this->skippedCount = max(0, $skippedCount);

        return $this;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function setErrorCount(int $errorCount): static
    {
        $this->errorCount = max(0, $errorCount);

        return $this;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setErrors(array $errors): static
    {
        $this->errors = $errors;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUndoneAt(): ?\DateTimeInterface
    {
        return $this->undoneAt;
    }
    public function markUndone(): static
    {
        $this->undoneAt = new \DateTime();
        return $this;
    }
    public function isUndone(): bool
    {
        return $this->undoneAt !== null;
    }
}
