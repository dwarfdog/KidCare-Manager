<?php

namespace App\Entity;

use App\Repository\CourseAuditLogRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseAuditLogRepository::class)]
#[ORM\Index(columns: ['user_id', 'created_at'], name: 'IDX_COURSE_AUDIT_USER_DATE')]
#[ORM\Index(columns: ['entity_type', 'entity_public_id'], name: 'IDX_COURSE_AUDIT_ENTITY')]
class CourseAuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 26, unique: true)]
    private string $publicId;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $actorIdentifier = null;

    #[ORM\Column(length: 80)]
    private string $action = '';

    #[ORM\Column(length: 80)]
    private string $entityType = '';

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $entityPublicId = null;

    #[ORM\Column(type: Types::JSON)]
    private array $context = [];

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

    public function setUser(?User $user): static
    {
        $this->user = $user;
        $this->actorIdentifier = $user?->getUserIdentifier();

        return $this;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function setEntity(string $entityType, ?string $entityPublicId): static
    {
        $this->entityType = $entityType;
        $this->entityPublicId = $entityPublicId;

        return $this;
    }

    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
