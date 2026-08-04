<?php

namespace App\Entity;

use App\Repository\CourseShoppingListItemRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseShoppingListItemRepository::class)]
#[ORM\Index(columns: ['shopping_list_id'], name: 'IDX_COURSE_LIST_ITEM_LIST')]
class CourseShoppingListItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 26, unique: true)]
    private string $publicId;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CourseShoppingList $shoppingList = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CourseProduct $product = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3)]
    private string $desiredQuantity = '1.000';

    #[ORM\Column(length: 12)]
    private string $unit = CourseProduct::UNIT_PIECE;

    #[ORM\Column(options: ['default' => false])]
    private bool $checked = false;

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

    public function getShoppingList(): ?CourseShoppingList
    {
        return $this->shoppingList;
    }

    public function setShoppingList(?CourseShoppingList $shoppingList): static
    {
        $this->shoppingList = $shoppingList;

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

    public function getDesiredQuantity(): float
    {
        return (float) $this->desiredQuantity;
    }

    public function setDesiredQuantity(float|string $desiredQuantity): static
    {
        $this->desiredQuantity = number_format((float) $desiredQuantity, 3, '.', '');

        return $this;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): static
    {
        $this->unit = in_array($unit, CourseProduct::UNITS, true) ? $unit : CourseProduct::UNIT_PIECE;

        return $this;
    }

    public function isChecked(): bool
    {
        return $this->checked;
    }

    public function setChecked(bool $checked): static
    {
        $this->checked = $checked;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
