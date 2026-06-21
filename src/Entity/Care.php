<?php

namespace App\Entity;

use App\Repository\CareRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CareRepository::class)]
#[ORM\Index(columns: ['user_id', 'nanny_id', 'date'], name: 'IDX_CARE_USER_NANNY_DATE')]
#[ORM\Index(columns: ['user_id', 'nanny_snapshot_key', 'month'], name: 'IDX_CARE_USER_NANNY_SNAPSHOT_MONTH')]
#[ORM\Index(columns: ['user_id', 'month'], name: 'IDX_CARE_USER_MONTH')]
class Care
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 26, unique: true)]
    private string $publicId;

    #[ORM\ManyToOne(inversedBy: 'cares')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'cares')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Nanny $nanny = null;

    #[ORM\Column(length: 255)]
    private string $nannyDisplayName = '';

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $nannySnapshotKey = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $mealsCount = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $hoursCount = '0.00';

    #[ORM\Column(length: 7)]
    private ?string $month = null;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $segmentGroup = null;

    #[ORM\Column(options: ['default' => 1])]
    private int $segmentIndex = 1;

    #[ORM\Column(options: ['default' => 1])]
    private int $segmentTotal = 1;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->publicId = UlidGenerator::generate();
        $this->segmentGroup = UlidGenerator::generate();
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

    public function setPublicId(string $publicId): static
    {
        $this->publicId = $publicId;

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

    public function getNanny(): ?Nanny
    {
        return $this->nanny;
    }

    public function setNanny(?Nanny $nanny): static
    {
        $this->nanny = $nanny;
        if ($nanny !== null) {
            $this->nannyDisplayName = $nanny->getFullName();
            $this->nannySnapshotKey = $nanny->getPublicId();
        }

        return $this;
    }

    public function getNannyDisplayName(): string
    {
        return $this->nannyDisplayName;
    }

    public function setNannyDisplayName(string $nannyDisplayName): static
    {
        $this->nannyDisplayName = trim($nannyDisplayName);

        return $this;
    }

    public function getNannySnapshotKey(): ?string
    {
        return $this->nannySnapshotKey;
    }

    public function setNannySnapshotKey(?string $nannySnapshotKey): static
    {
        $this->nannySnapshotKey = $nannySnapshotKey;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;
        $this->month = $date->format('Y-m');

        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function getMealsCount(): int
    {
        return $this->mealsCount;
    }

    public function setMealsCount(int $mealsCount): static
    {
        $this->mealsCount = max(0, $mealsCount);

        return $this;
    }

    public function getHoursCount(): float
    {
        return (float) $this->hoursCount;
    }

    public function setHoursCount(float|string $hoursCount): static
    {
        $this->hoursCount = number_format((float) $hoursCount, 2, '.', '');

        return $this;
    }

    public function getMonth(): ?string
    {
        return $this->month;
    }

    public function setMonth(string $month): static
    {
        $this->month = $month;

        return $this;
    }

    public function getSegmentGroup(): ?string
    {
        return $this->segmentGroup;
    }

    public function setSegmentGroup(?string $segmentGroup): static
    {
        $this->segmentGroup = $segmentGroup;

        return $this;
    }

    public function getSegmentIndex(): int
    {
        return $this->segmentIndex;
    }

    public function setSegmentIndex(int $segmentIndex): static
    {
        $this->segmentIndex = $segmentIndex;

        return $this;
    }

    public function getSegmentTotal(): int
    {
        return $this->segmentTotal;
    }

    public function setSegmentTotal(int $segmentTotal): static
    {
        $this->segmentTotal = $segmentTotal;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getStartDateTime(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->date->format('Y-m-d') . ' ' . $this->startTime->format('H:i:s'));
    }

    public function getEndDateTime(): \DateTimeImmutable
    {
        $end = new \DateTimeImmutable($this->date->format('Y-m-d') . ' ' . $this->endTime->format('H:i:s'));

        if ($end <= $this->getStartDateTime()) {
            $end = $end->modify('+1 day');
        }

        return $end;
    }

    public function isMultiSegment(): bool
    {
        return $this->segmentTotal > 1;
    }
}
