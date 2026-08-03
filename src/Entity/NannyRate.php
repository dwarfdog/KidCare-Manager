<?php

namespace App\Entity;

use App\Repository\NannyRateRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NannyRateRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_NANNY_RATE_EFFECTIVE_DATE', fields: ['user', 'nanny', 'effectiveFrom'])]
#[ORM\UniqueConstraint(name: 'UNIQ_NANNY_RATE_SNAPSHOT_EFFECTIVE_DATE', fields: ['user', 'nannySnapshotKey', 'effectiveFrom'])]
#[ORM\Index(columns: ['user_id', 'nanny_snapshot_key', 'effective_from'], name: 'IDX_NANNY_RATE_SNAPSHOT_DATE')]
class NannyRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 26, unique: true)]
    private string $publicId;

    #[ORM\ManyToOne(inversedBy: 'nannyRates')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'rates')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Nanny $nanny = null;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $nannySnapshotKey = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $hourlyRate = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $mealRate = '0.00';

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $effectiveFrom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $archivedAt = null;

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
            $this->nannySnapshotKey = $nanny->getPublicId();
        }

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

    public function getHourlyRate(): float
    {
        return (float) $this->hourlyRate;
    }

    public function setHourlyRate(float|string $hourlyRate): static
    {
        $this->hourlyRate = number_format((float) $hourlyRate, 2, '.', '');

        return $this;
    }

    public function getMealRate(): float
    {
        return (float) $this->mealRate;
    }

    public function setMealRate(float|string $mealRate): static
    {
        $this->mealRate = number_format((float) $mealRate, 2, '.', '');

        return $this;
    }

    public function getEffectiveFrom(): ?\DateTimeInterface
    {
        return $this->effectiveFrom;
    }

    public function setEffectiveFrom(\DateTimeInterface $effectiveFrom): static
    {
        $this->effectiveFrom = $effectiveFrom;

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

    public function getArchivedAt(): ?\DateTimeInterface { return $this->archivedAt; }
    public function archive(): static { $this->archivedAt = new \DateTime(); return $this; }
    public function isArchived(): bool { return $this->archivedAt !== null; }
}
