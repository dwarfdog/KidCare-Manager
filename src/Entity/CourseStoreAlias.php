<?php

namespace App\Entity;

use App\Repository\CourseStoreAliasRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseStoreAliasRepository::class)]
#[ORM\Index(columns: ['user_id', 'normalized_label'], name: 'IDX_COURSE_STORE_ALIAS_USER_LABEL')]
class CourseStoreAlias
{
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
    private ?CourseStore $store = null;

    #[ORM\Column(length: 255)]
    private string $normalizedLabel = '';

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

        return $this;
    }

    public function getNormalizedLabel(): string
    {
        return $this->normalizedLabel;
    }

    public function setNormalizedLabel(string $normalizedLabel): static
    {
        $this->normalizedLabel = self::normalize($normalizedLabel);

        return $this;
    }

    public static function normalize(string $label): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $label) ?? ''));
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
