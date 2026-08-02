<?php

namespace App\Entity;

use App\Repository\BankBudgetRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BankBudgetRepository::class)]
#[ORM\Index(columns: ['user_id', 'period_type', 'period_start'], name: 'IDX_BANK_BUDGET_USER_PERIOD')]
class BankBudget
{
    public const PERIOD_WEEK = 'week';
    public const PERIOD_MONTH = 'month';
    public const PERIOD_YEAR = 'year';
    public const PERIOD_CUSTOM = 'custom';

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
    private ?BankCategory $category = null;

    #[ORM\Column(length: 20)]
    private string $periodType = self::PERIOD_MONTH;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $periodStart;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $periodEnd;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $archivedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->publicId = UlidGenerator::generate();
        $this->periodStart = new \DateTime('first day of this month');
        $this->periodEnd = new \DateTime('last day of this month');
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

    public function getCategory(): ?BankCategory
    {
        return $this->category;
    }

    public function setCategory(?BankCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getPeriodType(): string
    {
        return $this->periodType;
    }

    public function setPeriodType(string $periodType): static
    {
        $this->periodType = $periodType;

        return $this;
    }

    public function getPeriodStart(): \DateTimeInterface
    {
        return $this->periodStart;
    }

    public function setPeriodStart(\DateTimeInterface $periodStart): static
    {
        $this->periodStart = $periodStart;

        return $this;
    }

    public function getPeriodEnd(): \DateTimeInterface
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(\DateTimeInterface $periodEnd): static
    {
        $this->periodEnd = $periodEnd;

        return $this;
    }

    public function getAmount(): float
    {
        return (float) $this->amount;
    }

    public function setAmount(float|string $amount): static
    {
        $this->amount = number_format(abs((float) $amount), 2, '.', '');

        return $this;
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
