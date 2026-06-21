<?php

namespace App\Entity;

use App\Repository\CareTemplateRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CareTemplateRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_TEMPLATE_USER_NANNY_NAME', fields: ['user', 'nanny', 'name'])]
class CareTemplate
{
    public const DAYS = [
        'monday' => 'Lundi',
        'tuesday' => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday' => 'Jeudi',
        'friday' => 'Vendredi',
        'saturday' => 'Samedi',
        'sunday' => 'Dimanche',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 26, unique: true)]
    private string $publicId;

    #[ORM\ManyToOne(inversedBy: 'careTemplates')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'careTemplates')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Nanny $nanny = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private array $weekSchedule = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->publicId = UlidGenerator::generate();
        $this->createdAt = new \DateTime();
        $this->weekSchedule = self::emptyWeekSchedule();
    }

    public static function emptyWeekSchedule(): array
    {
        $schedule = [];
        foreach (array_keys(self::DAYS) as $day) {
            $schedule[$day] = ['isActive' => false, 'slots' => []];
        }

        return $schedule;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getWeekSchedule(): array
    {
        return array_replace_recursive(self::emptyWeekSchedule(), $this->weekSchedule);
    }

    public function setWeekSchedule(array $weekSchedule): static
    {
        $this->weekSchedule = array_replace_recursive(self::emptyWeekSchedule(), $weekSchedule);

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
}
