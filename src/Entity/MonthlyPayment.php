<?php

namespace App\Entity;

use App\Repository\MonthlyPaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MonthlyPaymentRepository::class)]
class MonthlyPayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'monthlyPayments')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'monthlyPayments')]
    private ?Nanny $nanny = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $month = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?float $totalsHours = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private ?int $totalMeals = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?float $amountHours = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?float $amountMeals = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?float $totalAmount = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getMonth(): ?\DateTimeInterface
    {
        return $this->month;
    }

    public function setMonth(\DateTimeInterface $month): static
    {
        $this->month = $month;

        return $this;
    }

    public function getTotalsHours(): ?float
    {
        return $this->totalsHours;
    }

    public function setTotalsHours(float $totalsHours): static
    {
        $this->totalsHours = $totalsHours;

        return $this;
    }

    public function getTotalMeals(): ?int
    {
        return $this->totalMeals;
    }

    public function setTotalMeals(int $totalMeals): static
    {
        $this->totalMeals = $totalMeals;

        return $this;
    }

    public function getAmountHours(): ?float
    {
        return $this->amountHours;
    }

    public function setAmountHours(float $amountHours): static
    {
        $this->amountHours = $amountHours;

        return $this;
    }

    public function getAmountMeals(): ?float
    {
        return $this->amountMeals;
    }

    public function setAmountMeals(float $amountMeals): static
    {
        $this->amountMeals = $amountMeals;

        return $this;
    }

    public function getTotalAmount(): ?float
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(float $totalAmount): static
    {
        $this->totalAmount = $totalAmount;

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
