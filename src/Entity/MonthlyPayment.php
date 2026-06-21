<?php

namespace App\Entity;

use App\Repository\MonthlyPaymentRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MonthlyPaymentRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_PAYMENT_USER_NANNY_MONTH', fields: ['user', 'nanny', 'month'])]
#[ORM\UniqueConstraint(name: 'UNIQ_PAYMENT_USER_NANNY_SNAPSHOT_MONTH', fields: ['user', 'nannySnapshotKey', 'month'])]
#[ORM\Index(columns: ['user_id', 'month'], name: 'IDX_PAYMENT_USER_MONTH')]
class MonthlyPayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 26, unique: true)]
    private string $publicId;

    #[ORM\ManyToOne(inversedBy: 'monthlyPayments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'monthlyPayments')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Nanny $nanny = null;

    #[ORM\Column(length: 255)]
    private string $nannyDisplayName = '';

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $nannySnapshotKey = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $totalsHours = '0.00';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $totalMeals = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $amountHours = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $amountMeals = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $totalAmount = '0.00';

    #[ORM\Column(length: 7)]
    private ?string $month = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isPaid = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $paidAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->publicId = UlidGenerator::generate();
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

    public function getNanny(): ?Nanny
    {
        return $this->nanny;
    }

    public function setNanny(?Nanny $nanny): static
    {
        $this->nanny = $nanny;
        if ($nanny !== null) {
            $this->nannyDisplayName = $nanny->getFullName();
            $this->nannySnapshotKey = $nanny->getPublicId();
        }

        return $this;
    }

    public function getNannyDisplayName(): string
    {
        return $this->nannyDisplayName;
    }

    public function setNannyDisplayName(string $nannyDisplayName): static
    {
        $this->nannyDisplayName = trim($nannyDisplayName);

        return $this;
    }

    public function getNannySnapshotKey(): ?string
    {
        return $this->nannySnapshotKey;
    }

    public function setNannySnapshotKey(?string $nannySnapshotKey): static
    {
        $this->nannySnapshotKey = $nannySnapshotKey;

        return $this;
    }

    public function getTotalsHours(): float
    {
        return (float) $this->totalsHours;
    }

    public function setTotalsHours(float|string $totalsHours): static
    {
        $this->totalsHours = number_format((float) $totalsHours, 2, '.', '');
        $this->touch();

        return $this;
    }

    public function getTotalMeals(): int
    {
        return $this->totalMeals;
    }

    public function setTotalMeals(int $totalMeals): static
    {
        $this->totalMeals = max(0, $totalMeals);
        $this->touch();

        return $this;
    }

    public function getAmountHours(): float
    {
        return (float) $this->amountHours;
    }

    public function setAmountHours(float|string $amountHours): static
    {
        $this->amountHours = number_format((float) $amountHours, 2, '.', '');
        $this->touch();

        return $this;
    }

    public function getAmountMeals(): float
    {
        return (float) $this->amountMeals;
    }

    public function setAmountMeals(float|string $amountMeals): static
    {
        $this->amountMeals = number_format((float) $amountMeals, 2, '.', '');
        $this->touch();

        return $this;
    }

    public function getTotalAmount(): float
    {
        return (float) $this->totalAmount;
    }

    public function setTotalAmount(float|string $totalAmount): static
    {
        $this->totalAmount = number_format((float) $totalAmount, 2, '.', '');
        $this->touch();

        return $this;
    }

    public function getMonth(): ?string
    {
        return $this->month;
    }

    public function setMonth(string $month): static
    {
        $this->month = $month;

        return $this;
    }

    public function isPaid(): bool
    {
        return $this->isPaid;
    }

    public function setPaid(bool $isPaid): static
    {
        $this->isPaid = $isPaid;
        $this->paidAt = $isPaid ? new \DateTime() : null;
        $this->touch();

        return $this;
    }

    public function getPaidAt(): ?\DateTimeInterface
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeInterface $paidAt): static
    {
        $this->paidAt = $paidAt;
        $this->isPaid = $paidAt !== null;
        $this->touch();

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
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
