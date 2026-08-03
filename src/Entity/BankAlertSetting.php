<?php

namespace App\Entity;

use App\Repository\BankAlertSettingRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BankAlertSettingRepository::class)] #[ORM\Index(columns: ['user_id', 'target_type', 'target_public_id'], name: 'IDX_ALERT_TARGET')] class BankAlertSetting
{
    public const LOW_BALANCE = 'low_balance';
    public const BUDGET_RATE = 'budget_rate';
    public const BUDGET_OVER = 'budget_over';
    public const RECURRING_UPCOMING = 'recurring_upcoming';
    public const RECURRING_MISSING = 'recurring_missing';
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 26, unique: true)] private string $publicId;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?User $user = null;
    #[ORM\Column(length: 32)] private string $type = self::LOW_BALANCE;
    #[ORM\Column(length: 20)] private string $targetType = 'global';
    #[ORM\Column(length: 26, nullable: true)] private ?string $targetPublicId = null;
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $threshold = null;
    #[ORM\Column(options: ['default' => true])] private bool $active = true;
    #[ORM\Column(type: Types::DATETIME_MUTABLE)] private \DateTimeInterface $createdAt;
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
    public function setUser(?User $v): static
    {
        $this->user = $v;
        return $this;
    }
    public function getType(): string
    {
        return $this->type;
    }
    public function setType(string $v): static
    {
        $this->type = $v;
        return $this;
    }
    public function getTargetType(): string
    {
        return $this->targetType;
    }
    public function setTargetType(string $v): static
    {
        $this->targetType = $v;
        return $this;
    }
    public function getTargetPublicId(): ?string
    {
        return $this->targetPublicId;
    }
    public function setTargetPublicId(?string $v): static
    {
        $this->targetPublicId = $v ?: null;
        return $this;
    }
    public function getThreshold(): ?float
    {
        return $this->threshold !== null ? (float)$this->threshold : null;
    }
    public function setThreshold(float|string|null $v): static
    {
        $this->threshold = $v !== null && $v !== '' ? number_format((float)$v, 2, '.', '') : null;
        return $this;
    }
    public function isActive(): bool
    {
        return $this->active;
    }
    public function setActive(bool $v): static
    {
        $this->active = $v;
        return $this;
    }
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
