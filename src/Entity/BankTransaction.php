<?php

namespace App\Entity;

use App\Repository\BankTransactionRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BankTransactionRepository::class)]
#[ORM\Index(columns: ['user_id', 'account_id', 'transaction_date'], name: 'IDX_BANK_TRANSACTION_USER_ACCOUNT_DATE')]
#[ORM\Index(columns: ['user_id', 'category_id', 'transaction_date'], name: 'IDX_BANK_TRANSACTION_USER_CATEGORY_DATE')]
#[ORM\Index(columns: ['user_id', 'status', 'transaction_date'], name: 'IDX_BANK_TRANSACTION_USER_STATUS_DATE')]
#[ORM\Index(columns: ['user_id', 'verified_at'], name: 'IDX_BANK_TRANSACTION_USER_VERIFIED')]
#[ORM\UniqueConstraint(name: 'UNIQ_BANK_TRANSACTION_IMPORT_HASH', fields: ['user', 'account', 'importHash'])]
class BankTransaction
{
    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';
    public const TYPE_TRANSFER_IN = 'transfer_in';
    public const TYPE_TRANSFER_OUT = 'transfer_out';

    public const STATUS_PLANNED = 'planned';
    public const STATUS_REAL = 'real';
    public const STATUS_RECONCILED = 'reconciled';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_IMPORT = 'import';
    public const SOURCE_RECURRING = 'recurring';
    public const SOURCE_TRANSFER = 'transfer';
    public const SOURCE_KIDCARE = 'kidcare';
    public const SOURCE_LOAN = 'loan';

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
    private ?BankCategory $category = null;

    #[ORM\Column(name: 'transaction_date', type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $date;

    #[ORM\Column(length: 2048)]
    private string $encryptedLabel = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(length: 24)]
    private string $type = self::TYPE_EXPENSE;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_REAL;

    #[ORM\Column(length: 24)]
    private string $source = self::SOURCE_MANUAL;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $verifiedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $importHash = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $importedStateHash = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $reconciledAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?BankImportBatch $importBatch = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?BankRecurringRule $recurringRule = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?MonthlyPayment $monthlyPayment = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?BankAccount $loanAccount = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $mirrorTransaction = null;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $transferGroup = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $archivedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->publicId = UlidGenerator::generate();
        $this->date = new \DateTime('today');
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): string
    {
        return $this->publicId;
    }

    public function setPublicId(string $publicId): static
    {
        $this->publicId = $publicId;

        return $this;
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
        $this->touch();

        return $this;
    }

    public function getCategory(): ?BankCategory
    {
        return $this->category;
    }

    public function setCategory(?BankCategory $category): static
    {
        $this->category = $category;
        $this->touch();

        return $this;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;
        $this->touch();

        return $this;
    }

    public function getEncryptedLabel(): string
    {
        return $this->encryptedLabel;
    }

    public function setEncryptedLabel(string $encryptedLabel): static
    {
        $this->encryptedLabel = $encryptedLabel;
        $this->touch();

        return $this;
    }

    public function getAmount(): float
    {
        return (float) $this->amount;
    }

    public function setAmount(float|string $amount): static
    {
        $this->amount = number_format((float) $amount, 2, '.', '');
        $this->type = $this->inferTypeFromAmount((float) $this->amount);
        $this->touch();

        return $this;
    }

    public function setSignedAmount(float|string $amount, ?string $type = null): static
    {
        $numeric = (float) $amount;
        if ($type === self::TYPE_EXPENSE || $type === self::TYPE_TRANSFER_OUT) {
            $numeric = -abs($numeric);
        } elseif ($type === self::TYPE_INCOME || $type === self::TYPE_TRANSFER_IN) {
            $numeric = abs($numeric);
        }

        $this->amount = number_format($numeric, 2, '.', '');
        $this->type = $type ?? $this->inferTypeFromAmount($numeric);
        $this->touch();

        return $this;
    }

    public function getAbsoluteAmount(): float
    {
        return abs($this->getAmount());
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        $this->touch();

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->touch();

        return $this;
    }

    public function isReal(): bool
    {
        return in_array($this->status, [self::STATUS_REAL, self::STATUS_RECONCILED], true);
    }

    public function getVerifiedAt(): ?\DateTimeInterface
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeInterface $verifiedAt): static
    {
        $this->verifiedAt = $verifiedAt;
        $this->touch();

        return $this;
    }

    public function markVerified(?\DateTimeInterface $verifiedAt = null): static
    {
        return $this->setVerifiedAt($verifiedAt ?? new \DateTime());
    }

    public function markUnverified(): static
    {
        return $this->setVerifiedAt(null);
    }

    public function isVerified(): bool
    {
        return $this->verifiedAt !== null;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;
        $this->touch();

        return $this;
    }

    public function getImportHash(): ?string
    {
        return $this->importHash;
    }

    public function setImportHash(?string $importHash): static
    {
        $this->importHash = $importHash;

        return $this;
    }

    public function getImportedStateHash(): ?string
    {
        return $this->importedStateHash;
    }
    public function setImportedStateHash(?string $hash): static
    {
        $this->importedStateHash = $hash;
        return $this;
    }
    public function getReconciledAt(): ?\DateTimeInterface
    {
        return $this->reconciledAt;
    }
    public function setReconciledAt(?\DateTimeInterface $at): static
    {
        $this->reconciledAt = $at;
        $this->status = $at ? self::STATUS_RECONCILED : self::STATUS_REAL;
        $this->touch();
        return $this;
    }
    public function isReconciled(): bool
    {
        return $this->reconciledAt !== null || $this->status === self::STATUS_RECONCILED;
    }

    public function getImportBatch(): ?BankImportBatch
    {
        return $this->importBatch;
    }

    public function setImportBatch(?BankImportBatch $importBatch): static
    {
        $this->importBatch = $importBatch;

        return $this;
    }

    public function getRecurringRule(): ?BankRecurringRule
    {
        return $this->recurringRule;
    }

    public function setRecurringRule(?BankRecurringRule $recurringRule): static
    {
        $this->recurringRule = $recurringRule;

        return $this;
    }

    public function getMonthlyPayment(): ?MonthlyPayment
    {
        return $this->monthlyPayment;
    }

    public function setMonthlyPayment(?MonthlyPayment $monthlyPayment): static
    {
        $this->monthlyPayment = $monthlyPayment;

        return $this;
    }

    public function getLoanAccount(): ?BankAccount
    {
        return $this->loanAccount;
    }

    public function setLoanAccount(?BankAccount $loanAccount): static
    {
        $this->loanAccount = $loanAccount;

        return $this;
    }

    public function getMirrorTransaction(): ?self
    {
        return $this->mirrorTransaction;
    }

    public function setMirrorTransaction(?self $mirrorTransaction): static
    {
        $this->mirrorTransaction = $mirrorTransaction;

        return $this;
    }

    public function getTransferGroup(): ?string
    {
        return $this->transferGroup;
    }

    public function setTransferGroup(?string $transferGroup): static
    {
        $this->transferGroup = $transferGroup;

        return $this;
    }

    public function getArchivedAt(): ?\DateTimeInterface
    {
        return $this->archivedAt;
    }

    public function archive(): static
    {
        $this->archivedAt = new \DateTime();
        $this->touch();

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

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTime();

        return $this;
    }

    private function inferTypeFromAmount(float $amount): string
    {
        return $amount >= 0 ? self::TYPE_INCOME : self::TYPE_EXPENSE;
    }
}
