<?php

namespace App\Entity;

use App\Repository\CareRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: CareRepository::class)]
class Care
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'cares')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'cares')]
    private ?Nanny $nanny = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private ?int $mealsCount = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?float $hoursCount = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['slugBase'])]
    private ?string $slug = null;

    #[ORM\Column(length: 255)]
    private ?string $slugBase = null;

    public function getSlugBase(): string
    {
        return sprintf(
            '%s-%s-%s',
            $this->date?->format('Y-m-d'),
            $this->startTime?->format('H-i'),
            $this->endTime?->format('H-i')
        );
    }

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->slugBase = $this->getSlugBase();
    }

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

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

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

    public function getMealsCount(): ?int
    {
        return $this->mealsCount;
    }

    public function setMealsCount(int $mealsCount): static
    {
        $this->mealsCount = $mealsCount;

        return $this;
    }

    public function getHoursCount(): ?float
    {
        return $this->hoursCount;
    }

    public function setHoursCount(float $hoursCount): static
    {
        $this->hoursCount = $hoursCount;

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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }
}
