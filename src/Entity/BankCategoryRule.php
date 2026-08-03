<?php

namespace App\Entity;

use App\Repository\BankCategoryRuleRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BankCategoryRuleRepository::class)]
#[ORM\Index(columns: ['user_id', 'priority', 'archived_at'], name: 'IDX_BANK_RULE_PRIORITY')]
class BankCategoryRule
{
    public const MATCH_EXACT = 'exact';
    public const MATCH_CONTAINS = 'contains';
    public const MATCH_STARTS = 'starts';
    public const MATCH_REGEX = 'regex';
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 26, unique: true)] private string $publicId;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?User $user = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')] private ?BankAccount $account = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?BankCategory $category = null;
    #[ORM\Column(length: 16)] private string $matchType = self::MATCH_CONTAINS;
    #[ORM\Column(length: 255)] private string $pattern = '';
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $minimumAmount = null;
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)] private ?string $maximumAmount = null;
    #[ORM\Column(options: ['default' => 100])] private int $priority = 100;
    #[ORM\Column(options: ['default' => true])] private bool $active = true;
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)] private ?\DateTimeInterface $archivedAt = null;
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
    public function getAccount(): ?BankAccount
    {
        return $this->account;
    }
    public function setAccount(?BankAccount $v): static
    {
        $this->account = $v;
        return $this;
    }
    public function getCategory(): ?BankCategory
    {
        return $this->category;
    }
    public function setCategory(?BankCategory $v): static
    {
        $this->category = $v;
        return $this;
    }
    public function getMatchType(): string
    {
        return $this->matchType;
    }
    public function setMatchType(string $v): static
    {
        if (!in_array($v, [self::MATCH_EXACT, self::MATCH_CONTAINS, self::MATCH_STARTS, self::MATCH_REGEX], true)) throw new \InvalidArgumentException('Type de correspondance invalide.');
        $this->matchType = $v;
        return $this;
    }
    public function getPattern(): string
    {
        return $this->pattern;
    }
    public function setPattern(string $v): static
    {
        $this->pattern = trim($v);
        return $this;
    }
    public function getMinimumAmount(): ?float
    {
        return $this->minimumAmount !== null ? (float)$this->minimumAmount : null;
    }
    public function setMinimumAmount(float|string|null $v): static
    {
        $this->minimumAmount = $v !== null && $v !== '' ? number_format((float)$v, 2, '.', '') : null;
        return $this;
    }
    public function getMaximumAmount(): ?float
    {
        return $this->maximumAmount !== null ? (float)$this->maximumAmount : null;
    }
    public function setMaximumAmount(float|string|null $v): static
    {
        $this->maximumAmount = $v !== null && $v !== '' ? number_format((float)$v, 2, '.', '') : null;
        return $this;
    }
    public function getPriority(): int
    {
        return $this->priority;
    }
    public function setPriority(int $v): static
    {
        $this->priority = max(0, $v);
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
    public function getArchivedAt(): ?\DateTimeInterface
    {
        return $this->archivedAt;
    }
    public function archive(): static
    {
        $this->archivedAt = new \DateTime();
        $this->active = false;
        return $this;
    }
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
