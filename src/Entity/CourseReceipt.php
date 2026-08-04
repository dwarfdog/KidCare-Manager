<?php

namespace App\Entity;

use App\Repository\CourseReceiptRepository;
use App\Service\UlidGenerator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseReceiptRepository::class)]
#[ORM\Index(columns: ['user_id', 'status', 'purchase_date'], name: 'IDX_COURSE_RECEIPT_USER_STATUS_DATE')]
#[ORM\Index(columns: ['user_id', 'store_id'], name: 'IDX_COURSE_RECEIPT_USER_STORE')]
#[ORM\Index(columns: ['user_id', 'archived_at'], name: 'IDX_COURSE_RECEIPT_USER_ARCHIVE')]
class CourseReceipt
{
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_FAILED = 'failed';

    public const SOURCE_PHOTO = 'photo';
    public const SOURCE_PDF = 'pdf';
    public const SOURCE_TXT = 'txt';
    public const SOURCE_DOCX = 'docx';
    public const SOURCE_CSV = 'csv';
    public const SOURCE_XLSX = 'xlsx';
    public const SOURCE_MANUAL = 'manual';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 26, unique: true)]
    private string $publicId;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CourseStore $store = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?BankAccount $account = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?BankTransaction $bankTransaction = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $purchaseDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $totalAmount = '0.00';

    #[ORM\Column(length: 24)]
    private string $sourceFormat = self::SOURCE_MANUAL;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_PROCESSING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedOcrRawText = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $encryptedStoreNameGuess = null;

    #[ORM\Column(type: Types::JSON)]
    private array $processingErrors = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $confirmedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $archivedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    /** @var Collection<int, CourseReceiptLine> */
    #[ORM\OneToMany(mappedBy: 'receipt', targetEntity: CourseReceiptLine::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lines;

    public function __construct()
    {
        $this->publicId = UlidGenerator::generate();
        $this->purchaseDate = new \DateTime('today');
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->lines = new ArrayCollection();
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

    public function getStore(): ?CourseStore
    {
        return $this->store;
    }

    public function setStore(?CourseStore $store): static
    {
        $this->store = $store;
        $this->touch();

        return $this;
    }

    public function getAccount(): ?BankAccount
    {
        return $this->account;
    }

    public function setAccount(?BankAccount $account): static
    {
        $this->account = $account;
        $this->touch();

        return $this;
    }

    public function getBankTransaction(): ?BankTransaction
    {
        return $this->bankTransaction;
    }

    public function setBankTransaction(?BankTransaction $bankTransaction): static
    {
        $this->bankTransaction = $bankTransaction;
        $this->touch();

        return $this;
    }

    public function getPurchaseDate(): ?\DateTimeInterface
    {
        return $this->purchaseDate;
    }

    public function setPurchaseDate(?\DateTimeInterface $purchaseDate): static
    {
        $this->purchaseDate = $purchaseDate;
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

    public function getSourceFormat(): string
    {
        return $this->sourceFormat;
    }

    public function setSourceFormat(string $sourceFormat): static
    {
        $this->sourceFormat = $sourceFormat;

        return $this;
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

    public function isPendingReview(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function getEncryptedOcrRawText(): ?string
    {
        return $this->encryptedOcrRawText;
    }

    public function setEncryptedOcrRawText(?string $encryptedOcrRawText): static
    {
        $this->encryptedOcrRawText = $encryptedOcrRawText;

        return $this;
    }

    public function getEncryptedStoreNameGuess(): ?string
    {
        return $this->encryptedStoreNameGuess;
    }

    public function setEncryptedStoreNameGuess(?string $encryptedStoreNameGuess): static
    {
        $this->encryptedStoreNameGuess = $encryptedStoreNameGuess;

        return $this;
    }

    public function getProcessingErrors(): array
    {
        return $this->processingErrors;
    }

    public function setProcessingErrors(array $processingErrors): static
    {
        $this->processingErrors = $processingErrors;

        return $this;
    }

    public function getConfirmedAt(): ?\DateTimeInterface
    {
        return $this->confirmedAt;
    }

    public function markConfirmed(): static
    {
        $this->confirmedAt = new \DateTime();
        $this->status = self::STATUS_CONFIRMED;
        $this->touch();

        return $this;
    }

    public function getArchivedAt(): ?\DateTimeInterface
    {
        return $this->archivedAt;
    }

    public function archive(): static
    {
        $this->archivedAt = new \DateTime();
        $this->touch();

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

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /** @return Collection<int, CourseReceiptLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(CourseReceiptLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setReceipt($this);
        }

        return $this;
    }

    public function removeLine(CourseReceiptLine $line): static
    {
        $this->lines->removeElement($line);

        return $this;
    }
}
