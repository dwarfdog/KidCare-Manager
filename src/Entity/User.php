<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[UniqueEntity(fields: ['username'], message: 'There is already an account with this username')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $username = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    /**
     * @var Collection<int, Nanny>
     */
    #[ORM\ManyToMany(targetEntity: Nanny::class, mappedBy: 'user')]
    private Collection $nannies;

    /**
     * @var Collection<int, Care>
     */
    #[ORM\OneToMany(targetEntity: Care::class, mappedBy: 'user')]
    private Collection $cares;

    /**
     * @var Collection<int, MonthlyPayment>
     */
    #[ORM\OneToMany(targetEntity: MonthlyPayment::class, mappedBy: 'user')]
    private Collection $monthlyPayments;

    public function __construct()
    {
        $this->nannies = new ArrayCollection();
        $this->cares = new ArrayCollection();
        $this->monthlyPayments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * @return Collection<int, Nanny>
     */
    public function getNannies(): Collection
    {
        return $this->nannies;
    }

    public function addNanny(Nanny $nanny): static
    {
        if (!$this->nannies->contains($nanny)) {
            $this->nannies->add($nanny);
            $nanny->addUser($this);
        }

        return $this;
    }

    public function removeNanny(Nanny $nanny): static
    {
        if ($this->nannies->removeElement($nanny)) {
            $nanny->removeUser($this);
        }

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
            $care->setUser($this);
        }

        return $this;
    }

    public function removeCare(Care $care): static
    {
        if ($this->cares->removeElement($care)) {
            // set the owning side to null (unless already changed)
            if ($care->getUser() === $this) {
                $care->setUser(null);
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
            $monthlyPayment->setUser($this);
        }

        return $this;
    }

    public function removeMonthlyPayment(MonthlyPayment $monthlyPayment): static
    {
        if ($this->monthlyPayments->removeElement($monthlyPayment)) {
            // set the owning side to null (unless already changed)
            if ($monthlyPayment->getUser() === $this) {
                $monthlyPayment->setUser(null);
            }
        }

        return $this;
    }
}
