<?php

namespace App\Entity;

use App\Repository\CareAbsenceRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CareAbsenceRepository::class)]
#[ORM\Index(columns: ['user_id', 'nanny_id', 'starts_at'], name: 'IDX_CARE_ABSENCE_OWNER_PERIOD')]
class CareAbsence
{
    public const TYPE_CHILD_ABSENCE = 'child_absence';
    public const TYPE_NANNY_ABSENCE = 'nanny_absence';
    public const TYPE_PAID_LEAVE = 'paid_leave';
    public const TYPE_UNPAID_LEAVE = 'unpaid_leave';
    public const TYPE_CHILD_SICK_LEAVE = 'child_sick_leave';
    public const TYPE_NANNY_SICK_LEAVE = 'nanny_sick_leave';
    public const TYPE_CLOSURE = 'closure';
    public const TYPE_OTHER = 'other';

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
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Nanny $nanny = null;

    #[ORM\Column(length: 32)]
    private string $type = self::TYPE_OTHER;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $startsAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $endsAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $remunerated = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $archivedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->publicId = UlidGenerator::generate();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getPublicId(): string { return $this->publicId; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getNanny(): ?Nanny { return $this->nanny; }
    public function setNanny(?Nanny $nanny): static { $this->nanny = $nanny; return $this; }
    public function getType(): string { return $this->type; }

    public function setType(string $type): static
    {
        if (!array_key_exists($type, self::typeLabels())) {
            throw new \InvalidArgumentException('Motif d\'absence invalide.');
        }
        $this->type = $type;
        return $this;
    }

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_CHILD_ABSENCE => 'Absence de l\'enfant',
            self::TYPE_NANNY_ABSENCE => 'Absence de la nounou',
            self::TYPE_PAID_LEAVE => 'Congé payé',
            self::TYPE_UNPAID_LEAVE => 'Congé non payé',
            self::TYPE_CHILD_SICK_LEAVE => 'Arrêt maladie de l\'enfant',
            self::TYPE_NANNY_SICK_LEAVE => 'Arrêt maladie de la nounou',
            self::TYPE_CLOSURE => 'Fermeture',
            self::TYPE_OTHER => 'Autre annulation',
        ];
    }

    public function getTypeLabel(): string { return self::typeLabels()[$this->type] ?? $this->type; }
    public function getStartsAt(): ?\DateTimeInterface { return $this->startsAt; }
    public function setStartsAt(\DateTimeInterface $startsAt): static { $this->startsAt = $startsAt; return $this; }
    public function getEndsAt(): ?\DateTimeInterface { return $this->endsAt; }
    public function setEndsAt(\DateTimeInterface $endsAt): static { $this->endsAt = $endsAt; return $this; }
    public function isRemunerated(): bool { return $this->remunerated; }
    public function setRemunerated(bool $remunerated): static { $this->remunerated = $remunerated; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $notes = $notes !== null ? trim($notes) : null; $this->notes = $notes !== '' ? $notes : null; return $this; }
    public function getArchivedAt(): ?\DateTimeInterface { return $this->archivedAt; }
    public function archive(): static { $this->archivedAt = new \DateTime(); return $this; }
    public function isArchived(): bool { return $this->archivedAt !== null; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
