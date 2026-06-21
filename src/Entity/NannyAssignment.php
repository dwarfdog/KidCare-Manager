<?php

namespace App\Entity;

use App\Repository\NannyAssignmentRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NannyAssignmentRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_NANNY_ASSIGNMENT_USER_NANNY', fields: ['user', 'nanny'])]
class NannyAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 26, unique: true)]
    private string $publicId;

    #[ORM\ManyToOne(inversedBy: 'nannyAssignments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'assignments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Nanny $nanny = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $attachedAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $detachedAt = null;

    public function __construct()
    {
        $this->publicId = UlidGenerator::generate();
        $this->attachedAt = new \DateTime();
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

        return $this;
    }

    public function getAttachedAt(): \DateTimeInterface
    {
        return $this->attachedAt;
    }

    public function setAttachedAt(\DateTimeInterface $attachedAt): static
    {
        $this->attachedAt = $attachedAt;

        return $this;
    }

    public function getDetachedAt(): ?\DateTimeInterface
    {
        return $this->detachedAt;
    }

    public function setDetachedAt(?\DateTimeInterface $detachedAt): static
    {
        $this->detachedAt = $detachedAt;

        return $this;
    }
}
