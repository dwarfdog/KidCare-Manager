<?php

namespace App\Entity;

use App\Repository\BankAssetRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BankAssetRepository::class)] class BankAsset
{
    public const TYPE_ASSET = 'asset';
    public const TYPE_DEBT = 'debt';
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 26, unique: true)] private string $publicId;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?User $user = null;
    #[ORM\Column(length: 120)] private string $name = '';
    #[ORM\Column(length: 16)] private string $type = self::TYPE_ASSET;
    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)] private string $currentValue = '0.00';
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
    public function getName(): string
    {
        return $this->name;
    }
    public function setName(string $v): static
    {
        $this->name = trim($v);
        return $this;
    }
    public function getType(): string
    {
        return $this->type;
    }
    public function setType(string $v): static
    {
        if (!in_array($v, [self::TYPE_ASSET, self::TYPE_DEBT], true)) throw new \InvalidArgumentException('Type de patrimoine invalide.');
        $this->type = $v;
        return $this;
    }
    public function getCurrentValue(): float
    {
        return (float)$this->currentValue;
    }
    public function setCurrentValue(float|string $v): static
    {
        $this->currentValue = number_format(abs((float)$v), 2, '.', '');
        return $this;
    }
    public function signedValue(): float
    {
        return $this->type === self::TYPE_DEBT ? -$this->getCurrentValue() : $this->getCurrentValue();
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
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
