<?php

namespace App\Entity;

use App\Repository\CourseReceiptLineRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseReceiptLineRepository::class)]
#[ORM\Index(columns: ['user_id', 'product_id'], name: 'IDX_COURSE_LINE_USER_PRODUCT')]
#[ORM\Index(columns: ['receipt_id'], name: 'IDX_COURSE_LINE_RECEIPT')]
class CourseReceiptLine
{
    public const CONFIDENCE_LOW = 'low';
    public const CONFIDENCE_MEDIUM = 'medium';
    public const CONFIDENCE_HIGH = 'high';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 26, unique: true)]
    private string $publicId;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CourseReceipt $receipt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 2048)]
    private string $encryptedRawLabel = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CourseProduct $product = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CourseCategory $category = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3)]
    private string $quantity = '1.000';

    #[ORM\Column(length: 12)]
    private string $unit = CourseProduct::UNIT_PIECE;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $unitPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $lineTotal = '0.00';

    #[ORM\Column(length: 12)]
    private string $confidence = self::CONFIDENCE_LOW;

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

    public function getReceipt(): ?CourseReceipt
    {
        return $this->receipt;
    }

    public function setReceipt(?CourseReceipt $receipt): static
    {
        $this->receipt = $receipt;

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

    public function getEncryptedRawLabel(): string
    {
        return $this->encryptedRawLabel;
    }

    public function setEncryptedRawLabel(string $encryptedRawLabel): static
    {
        $this->encryptedRawLabel = $encryptedRawLabel;

        return $this;
    }

    public function getProduct(): ?CourseProduct
    {
        return $this->product;
    }

    public function setProduct(?CourseProduct $product): static
    {
        $this->product = $product;

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

    public function getQuantity(): float
    {
        return (float) $this->quantity;
    }

    public function setQuantity(float|string $quantity): static
    {
        $this->quantity = number_format((float) $quantity, 3, '.', '');

        return $this;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): static
    {
        $this->unit = in_array($unit, CourseProduct::UNITS, true) ? $unit : CourseProduct::UNIT_PIECE;

        return $this;
    }

    public function getUnitPrice(): ?float
    {
        return $this->unitPrice !== null ? (float) $this->unitPrice : null;
    }

    public function setUnitPrice(float|string|null $unitPrice): static
    {
        $this->unitPrice = $unitPrice !== null && $unitPrice !== ''
            ? number_format((float) $unitPrice, 4, '.', '')
            : null;

        return $this;
    }

    public function getLineTotal(): float
    {
        return (float) $this->lineTotal;
    }

    public function setLineTotal(float|string $lineTotal): static
    {
        $this->lineTotal = number_format((float) $lineTotal, 2, '.', '');

        return $this;
    }

    public function getConfidence(): string
    {
        return $this->confidence;
    }

    public function setConfidence(string $confidence): static
    {
        $this->confidence = $confidence;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
