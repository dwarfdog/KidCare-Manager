<?php

namespace App\Entity;

use App\Repository\BankAccountRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BankAccountRepository::class)]
#[ORM\Index(columns: ['user_id', 'archived_at'], name: 'IDX_BANK_ACCOUNT_USER_ARCHIVE')]
class BankAccount
{
    public const TYPE_CURRENT = 'current';
    public const TYPE_SAVINGS = 'savings';
    public const TYPE_DEFERRED_CARD = 'deferred_card';
    public const TYPE_CREDIT = 'credit';
    public const TYPE_LOAN = 'loan';
    public const TYPE_CASH = 'cash';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 26, unique: true)]
    private string $publicId;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 1024)]
    private string $encryptedName = '';

    #[ORM\Column(length: 32)]
    private string $type = self::TYPE_CURRENT;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $initialBalance = '0.00';

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $initialBalanceDate;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $encryptedIban = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $loanInitialCapital = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $loanRemainingCapital = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 3, nullable: true)]
    private ?string $loanAnnualRate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $loanEndDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $loanMonthlyPayment = null;

    #[ORM\Column(nullable: true)]
    private ?int $loanPaymentDay = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?BankAccount $loanDebitAccount = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?BankCategory $loanCategory = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $archivedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->publicId = UlidGenerator::generate();
        $this->initialBalanceDate = new \DateTime('today');
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

    public function getEncryptedName(): string
    {
        return $this->encryptedName;
    }

    public function setEncryptedName(string $encryptedName): static
    {
        $this->encryptedName = $encryptedName;
        $this->touch();

        return $this;
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

    public function getInitialBalance(): float
    {
        return (float) $this->initialBalance;
    }

    public function setInitialBalance(float|string $initialBalance): static
    {
        $this->initialBalance = number_format((float) $initialBalance, 2, '.', '');
        $this->touch();

        return $this;
    }

    public function getInitialBalanceDate(): \DateTimeInterface
    {
        return $this->initialBalanceDate;
    }

    public function setInitialBalanceDate(\DateTimeInterface $initialBalanceDate): static
    {
        $this->initialBalanceDate = $initialBalanceDate;
        $this->touch();

        return $this;
    }

    public function getEncryptedIban(): ?string
    {
        return $this->encryptedIban;
    }

    public function setEncryptedIban(?string $encryptedIban): static
    {
        $this->encryptedIban = $encryptedIban;
        $this->touch();

        return $this;
    }

    public function getLoanInitialCapital(): ?float
    {
        return $this->loanInitialCapital !== null ? (float) $this->loanInitialCapital : null;
    }

    public function setLoanInitialCapital(float|string|null $loanInitialCapital): static
    {
        $this->loanInitialCapital = $loanInitialCapital !== null && $loanInitialCapital !== ''
            ? number_format(max(0, (float) $loanInitialCapital), 2, '.', '')
            : null;
        $this->touch();

        return $this;
    }

    public function getLoanRemainingCapital(): ?float
    {
        return $this->loanRemainingCapital !== null ? (float) $this->loanRemainingCapital : null;
    }

    public function setLoanRemainingCapital(float|string|null $loanRemainingCapital): static
    {
        $this->loanRemainingCapital = $loanRemainingCapital !== null && $loanRemainingCapital !== ''
            ? number_format(max(0, (float) $loanRemainingCapital), 2, '.', '')
            : null;
        $this->touch();

        return $this;
    }

    public function getLoanAnnualRate(): ?float
    {
        return $this->loanAnnualRate !== null ? (float) $this->loanAnnualRate : null;
    }

    public function setLoanAnnualRate(float|string|null $loanAnnualRate): static
    {
        $this->loanAnnualRate = $loanAnnualRate !== null && $loanAnnualRate !== ''
            ? number_format(max(0, (float) $loanAnnualRate), 3, '.', '')
            : null;
        $this->touch();

        return $this;
    }

    public function getLoanEndDate(): ?\DateTimeInterface
    {
        return $this->loanEndDate;
    }

    public function setLoanEndDate(?\DateTimeInterface $loanEndDate): static
    {
        $this->loanEndDate = $loanEndDate;
        $this->touch();

        return $this;
    }

    public function getLoanMonthlyPayment(): ?float
    {
        return $this->loanMonthlyPayment !== null ? (float) $this->loanMonthlyPayment : null;
    }

    public function setLoanMonthlyPayment(float|string|null $loanMonthlyPayment): static
    {
        $this->loanMonthlyPayment = $loanMonthlyPayment !== null && $loanMonthlyPayment !== ''
            ? number_format(max(0, (float) $loanMonthlyPayment), 2, '.', '')
            : null;
        $this->touch();

        return $this;
    }

    public function getLoanPaymentDay(): ?int
    {
        return $this->loanPaymentDay;
    }

    public function setLoanPaymentDay(?int $loanPaymentDay): static
    {
        $this->loanPaymentDay = $loanPaymentDay !== null ? max(1, min(31, $loanPaymentDay)) : null;
        $this->touch();

        return $this;
    }

    public function getLoanDebitAccount(): ?self
    {
        return $this->loanDebitAccount;
    }

    public function setLoanDebitAccount(?self $loanDebitAccount): static
    {
        $this->loanDebitAccount = $loanDebitAccount;
        $this->touch();

        return $this;
    }

    public function getLoanCategory(): ?BankCategory
    {
        return $this->loanCategory;
    }

    public function setLoanCategory(?BankCategory $loanCategory): static
    {
        $this->loanCategory = $loanCategory;
        $this->touch();

        return $this;
    }

    public function decreaseLoanRemainingCapital(float $amount): static
    {
        if ($this->loanRemainingCapital === null) {
            return $this;
        }

        return $this->setLoanRemainingCapital(max(0, (float) $this->loanRemainingCapital - abs($amount)));
    }

    public function isLoan(): bool
    {
        return $this->type === self::TYPE_LOAN;
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

    public function restore(): static
    {
        $this->archivedAt = null;
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
}
