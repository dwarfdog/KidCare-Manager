<?php

namespace App\Entity;

use App\Repository\CareTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: CareTemplateRepository::class)]
class CareTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'careTemplates')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'careTemplates')]
    private ?Nanny $nanny = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'])]
    private ?string $slug = null;

    #[ORM\Column]
    private array $weekSchedule = [
        'monday' => [
            'isActive' => false,
            'slots' => []
        ],
        'tuesday' => [
            'isActive' => false,
            'slots' => []
        ],
        'wednesday' => [
            'isActive' => false,
            'slots' => []
        ],
        'thursday' => [
            'isActive' => false,
            'slots' => []
        ],
        'friday' => [
            'isActive' => false,
            'slots' => []
        ]
    ];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getWeekSchedule(): array
    {
        return $this->weekSchedule;
    }

    public function setWeekSchedule(array $weekSchedule): static
    {
        $this->weekSchedule = $weekSchedule;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
