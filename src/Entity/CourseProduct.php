<?php

namespace App\Entity;

use App\Repository\CourseProductRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseProductRepository::class)]
#[ORM\Index(columns: ['user_id', 'archived_at'], name: 'IDX_COURSE_PRODUCT_USER_ARCHIVE')]
#[ORM\Index(columns: ['user_id', 'category_id'], name: 'IDX_COURSE_PRODUCT_USER_CATEGORY')]
class CourseProduct
{
    public const UNIT_PIECE = 'piece';
    public const UNIT_KG = 'kg';
    public const UNIT_G = 'g';
    public const UNIT_L = 'l';
    public const UNIT_ML = 'ml';

    public const UNITS = [self::UNIT_PIECE, self::UNIT_KG, self::UNIT_G, self::UNIT_L, self::UNIT_ML];

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

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CourseCategory $category = null;

    #[ORM\Column(length: 12)]
    private string $defaultUnit = self::UNIT_PIECE;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
    private ?string $packageQuantity = null;

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

    public function getCategory(): ?CourseCategory
    {
        return $this->category;
    }

    public function setCategory(?CourseCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getDefaultUnit(): string
    {
        return $this->defaultUnit;
    }

    public function setDefaultUnit(string $defaultUnit): static
    {
        $this->defaultUnit = in_array($defaultUnit, self::UNITS, true) ? $defaultUnit : self::UNIT_PIECE;

        return $this;
    }

    public function getPackageQuantity(): ?float
    {
        return $this->packageQuantity !== null ? (float) $this->packageQuantity : null;
    }

    public function setPackageQuantity(float|string|null $packageQuantity): static
    {
        $this->packageQuantity = $packageQuantity !== null && $packageQuantity !== ''
            ? number_format((float) $packageQuantity, 3, '.', '')
            : null;

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
