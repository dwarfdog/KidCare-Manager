<?php

namespace App\Entity;

use App\Repository\CourseStoreRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseStoreRepository::class)]
#[ORM\Index(columns: ['user_id', 'archived_at'], name: 'IDX_COURSE_STORE_USER_ARCHIVE')]
#[ORM\Index(columns: ['user_id', 'chain'], name: 'IDX_COURSE_STORE_USER_CHAIN')]
class CourseStore
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 26, unique: true)]
    private string $publicId;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 2048)]
    private string $encryptedName = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $chain = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $encryptedAddress = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $archivedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

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

        return $this;
    }

    public function getChain(): ?string
    {
        return $this->chain;
    }

    public function setChain(?string $chain): static
    {
        $chain = trim((string) $chain);
        $this->chain = $chain !== '' ? $chain : null;

        return $this;
    }

    public function getEncryptedAddress(): ?string
    {
        return $this->encryptedAddress;
    }

    public function setEncryptedAddress(?string $encryptedAddress): static
    {
        $this->encryptedAddress = $encryptedAddress;

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
