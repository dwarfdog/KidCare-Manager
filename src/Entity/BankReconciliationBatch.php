<?php

namespace App\Entity;

use App\Repository\BankReconciliationBatchRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BankReconciliationBatchRepository::class)] class BankReconciliationBatch
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 26, unique: true)] private string $publicId;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?User $user = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?BankAccount $account = null;
    #[ORM\Column(length: 255)] private string $filename = '';
    #[ORM\Column(options: ['default' => 0])] private int $matchedCount = 0;
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
    public function getFilename(): string
    {
        return $this->filename;
    }
    public function setFilename(string $v): static
    {
        $this->filename = $v;
        return $this;
    }
    public function getMatchedCount(): int
    {
        return $this->matchedCount;
    }
    public function setMatchedCount(int $v): static
    {
        $this->matchedCount = max(0, $v);
        return $this;
    }
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
