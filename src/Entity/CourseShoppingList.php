<?php

namespace App\Entity;

use App\Repository\CourseShoppingListRepository;
use App\Service\UlidGenerator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseShoppingListRepository::class)]
#[ORM\Index(columns: ['user_id', 'archived_at'], name: 'IDX_COURSE_SHOPPING_LIST_USER_ARCHIVE')]
class CourseShoppingList
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

    #[ORM\Column(length: 160)]
    private string $name = '';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $archivedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    /** @var Collection<int, CourseShoppingListItem> */
    #[ORM\OneToMany(mappedBy: 'shoppingList', targetEntity: CourseShoppingListItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->publicId = UlidGenerator::generate();
        $this->createdAt = new \DateTime();
        $this->items = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function markCompleted(): static
    {
        $this->completedAt = new \DateTime();

        return $this;
    }

    public function reopen(): static
    {
        $this->completedAt = null;

        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->completedAt !== null;
    }

    public function getArchivedAt(): ?\DateTimeInterface
    {
        return $this->archivedAt;
    }

    public function archive(): static
    {
        $this->archivedAt = new \DateTime();

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    /** @return Collection<int, CourseShoppingListItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(CourseShoppingListItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setShoppingList($this);
        }

        return $this;
    }

    public function removeItem(CourseShoppingListItem $item): static
    {
        $this->items->removeElement($item);

        return $this;
    }
}
