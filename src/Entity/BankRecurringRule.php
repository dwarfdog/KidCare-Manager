<?php

namespace App\Entity;

use App\Repository\BankRecurringRuleRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BankRecurringRuleRepository::class)]
#[ORM\Index(columns: ['user_id', 'is_active', 'start_date'], name: 'IDX_BANK_RECURRING_USER_ACTIVE_START')]
class BankRecurringRule
{
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_YEARLY = 'yearly';
    public const FREQUENCY_CUSTOM_DAYS = 'custom_days';

    public const ATTACHMENT_CURRENT = 'current';
    public const ATTACHMENT_NEXT = 'next';

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

    #[ORM\Column(length: 2048)]
    private string $encryptedLabel = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(length: 24)]
    private string $frequency = self::FREQUENCY_MONTHLY;

    #[ORM\Column(nullable: true)]
    private ?int $intervalDays = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $startDate;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $isSalaryReference = false;

    /** Rattachement au previsionnel: comptee dans la periode ou elle tombe, ou reservee a la periode suivante. */
    #[ORM\Column(length: 16, options: ['default' => self::ATTACHMENT_CURRENT])]
    private string $periodAttachment = self::ATTACHMENT_CURRENT;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->publicId = UlidGenerator::generate();
        $this->startDate = new \DateTime('today');
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

    public function setSignedAmount(float|string $amount): static
    {
        $this->amount = number_format((float) $amount, 2, '.', '');
        $this->touch();

        return $this;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): static
    {
        $this->frequency = $frequency;
        $this->touch();

        return $this;
    }

    public function getIntervalDays(): ?int
    {
        return $this->intervalDays;
    }

    public function setIntervalDays(?int $intervalDays): static
    {
        $this->intervalDays = $intervalDays !== null ? max(1, $intervalDays) : null;
        $this->touch();

        return $this;
    }

    public function getStartDate(): \DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        $this->touch();

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        $this->touch();

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        $this->touch();

        return $this;
    }

    public function isSalaryReference(): bool
    {
        return $this->isSalaryReference;
    }

    public function setSalaryReference(bool $isSalaryReference): static
    {
        $this->isSalaryReference = $isSalaryReference;
        $this->touch();

        return $this;
    }

    public function getPeriodAttachment(): string
    {
        return $this->periodAttachment;
    }

    public function setPeriodAttachment(string $periodAttachment): static
    {
        $this->periodAttachment = in_array($periodAttachment, [self::ATTACHMENT_CURRENT, self::ATTACHMENT_NEXT], true)
            ? $periodAttachment
            : self::ATTACHMENT_CURRENT;
        $this->touch();

        return $this;
    }

    public function isNextPeriodAttached(): bool
    {
        return $this->periodAttachment === self::ATTACHMENT_NEXT;
    }

    public function isIncome(): bool
    {
        return $this->getAmount() >= 0;
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
