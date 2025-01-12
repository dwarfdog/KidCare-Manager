<?php

namespace App\Entity;

use App\Repository\NannyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: NannyRepository::class)]
class Nanny
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, options: ['default' => 'prénom'])]
    private ?string $firstname = null;

    #[ORM\Column(length: 255, options: ['default' => 'nom'])]
    private ?string $lastname = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?float $hourlyRate = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?float $mealRate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'nannies')]
    private Collection $user;

    /**
     * @var Collection<int, Care>
     */
    #[ORM\OneToMany(targetEntity: Care::class, mappedBy: 'nanny')]
    private Collection $cares;

    /**
     * @var Collection<int, MonthlyPayment>
     */
    #[ORM\OneToMany(targetEntity: MonthlyPayment::class, mappedBy: 'nanny')]
    private Collection $monthlyPayments;

    #[ORM\Column(length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['firstname', 'lastname'])]
    private ?string $slug = null;

    /**
     * @var Collection<int, CareTemplate>
     */
    #[ORM\OneToMany(targetEntity: CareTemplate::class, mappedBy: 'nanny')]
    private Collection $careTemplates;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->user = new ArrayCollection();
        $this->cares = new ArrayCollection();
        $this->monthlyPayments = new ArrayCollection();
        $this->careTemplates = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getHourlyRate(): ?float
    {
        return $this->hourlyRate;
    }

    public function setHourlyRate(float $hourlyRate): static
    {
        $this->hourlyRate = $hourlyRate;

        return $this;
    }

    public function getMealRate(): ?float
    {
        return $this->mealRate;
    }

    public function setMealRate(float $mealRate): static
    {
        $this->mealRate = $mealRate;

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

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->user;
    }

    public function addUser(User $user): static
    {
        if (!$this->user->contains($user)) {
            $this->user->add($user);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        $this->user->removeElement($user);

        return $this;
    }

    /**
     * @return Collection<int, Care>
     */
    public function getCares(): Collection
    {
        return $this->cares;
    }

    public function addCare(Care $care): static
    {
        if (!$this->cares->contains($care)) {
            $this->cares->add($care);
            $care->setNanny($this);
        }

        return $this;
    }

    public function removeCare(Care $care): static
    {
        if ($this->cares->removeElement($care)) {
            // set the owning side to null (unless already changed)
            if ($care->getNanny() === $this) {
                $care->setNanny(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, MonthlyPayment>
     */
    public function getMonthlyPayments(): Collection
    {
        return $this->monthlyPayments;
    }

    public function addMonthlyPayment(MonthlyPayment $monthlyPayment): static
    {
        if (!$this->monthlyPayments->contains($monthlyPayment)) {
            $this->monthlyPayments->add($monthlyPayment);
            $monthlyPayment->setNanny($this);
        }

        return $this;
    }

    public function removeMonthlyPayment(MonthlyPayment $monthlyPayment): static
    {
        if ($this->monthlyPayments->removeElement($monthlyPayment)) {
            // set the owning side to null (unless already changed)
            if ($monthlyPayment->getNanny() === $this) {
                $monthlyPayment->setNanny(null);
            }
        }

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

    public function getFullname(): string
    {
        return $this->firstname . ' ' . $this->lastname;
    }

    /**
     * @return Collection<int, CareTemplate>
     */
    public function getCareTemplates(): Collection
    {
        return $this->careTemplates;
    }

    public function addCareTemplate(CareTemplate $careTemplate): static
    {
        if (!$this->careTemplates->contains($careTemplate)) {
            $this->careTemplates->add($careTemplate);
            $careTemplate->setNanny($this);
        }

        return $this;
    }

    public function removeCareTemplate(CareTemplate $careTemplate): static
    {
        if ($this->careTemplates->removeElement($careTemplate)) {
            // set the owning side to null (unless already changed)
            if ($careTemplate->getNanny() === $this) {
                $careTemplate->setNanny(null);
            }
        }

        return $this;
    }
}
