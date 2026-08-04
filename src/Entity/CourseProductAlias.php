<?php

namespace App\Entity;

use App\Repository\CourseProductAliasRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseProductAliasRepository::class)]
#[ORM\Index(columns: ['user_id', 'store_id', 'normalized_label'], name: 'IDX_COURSE_PRODUCT_ALIAS_USER_STORE_LABEL')]
#[ORM\UniqueConstraint(name: 'UNIQ_COURSE_PRODUCT_ALIAS_USER_STORE_LABEL', columns: ['user_id', 'store_id', 'normalized_label'])]
class CourseProductAlias
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

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CourseProduct $product = null;

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

    public function getProduct(): ?CourseProduct
    {
        return $this->product;
    }

    public function setProduct(?CourseProduct $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getNormalizedLabel(): string
    {
        return $this->normalizedLabel;
    }

    public function setNormalizedLabel(string $normalizedLabel): static
    {
        $this->normalizedLabel = CourseStoreAlias::normalize($normalizedLabel);

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
