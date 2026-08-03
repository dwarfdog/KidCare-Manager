<?php

namespace App\Entity;

use App\Repository\BankAnalyticsFavoriteRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BankAnalyticsFavoriteRepository::class)] class BankAnalyticsFavorite
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 26, unique: true)] private string $publicId;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?User $user = null;
    #[ORM\Column(length: 120)] private string $name = '';
    #[ORM\Column(type: Types::JSON)] private array $filters = [];
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
    public function getFilters(): array
    {
        return $this->filters;
    }
    public function setFilters(array $v): static
    {
        $this->filters = $v;
        return $this;
    }
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
