<?php

namespace App\Entity;

use App\Repository\BankGoalRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BankGoalRepository::class)]
#[ORM\Index(columns: ['user_id', 'status'], name: 'IDX_BANK_GOAL_USER_STATUS')]
class BankGoal
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ACHIEVED = 'achieved';
    public const STATUS_ARCHIVED = 'archived';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 26, unique: true)]
    private string $publicId;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 140)]
    private string $name = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $targetAmount = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $currentAmount = '0.00';

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $targetDate = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?BankAccount $linkedAccount = null;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_ACTIVE;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);
        $this->touch();

        return $this;
    }

    public function getTargetAmount(): float
    {
        return (float) $this->targetAmount;
    }

    public function setTargetAmount(float|string $targetAmount): static
    {
        $this->targetAmount = number_format(max(0, (float) $targetAmount), 2, '.', '');
        $this->touch();

        return $this;
    }

    public function getCurrentAmount(): float
    {
        return (float) $this->currentAmount;
    }

    public function setCurrentAmount(float|string $currentAmount): static
    {
        $this->currentAmount = number_format(max(0, (float) $currentAmount), 2, '.', '');
        $this->touch();

        return $this;
    }

    public function getCompletionRate(): float
    {
        if ($this->getTargetAmount() <= 0.0) {
            return 0.0;
        }

        return min(100.0, round($this->getCurrentAmount() / $this->getTargetAmount() * 100, 2));
    }

    public function getTargetDate(): ?\DateTimeInterface
    {
        return $this->targetDate;
    }

    public function setTargetDate(?\DateTimeInterface $targetDate): static
    {
        $this->targetDate = $targetDate;
        $this->touch();

        return $this;
    }

    public function getLinkedAccount(): ?BankAccount
    {
        return $this->linkedAccount;
    }

    public function setLinkedAccount(?BankAccount $linkedAccount): static
    {
        $this->linkedAccount = $linkedAccount;
        $this->touch();

        return $this;
    }

    public function addToCurrentAmount(float $amount): static
    {
        return $this->setCurrentAmount($this->getCurrentAmount() + $amount);
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
