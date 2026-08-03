<?php

namespace App\Entity;

use App\Repository\NannyRepository;
use App\Service\UlidGenerator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NannyRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_NANNY_IDENTITY_LOCATION', fields: ['firstname', 'lastname', 'postalCode', 'city'])]
class Nanny
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 26, unique: true)]
    private string $publicId;

    #[ORM\Column(length: 120)]
    private ?string $firstname = null;

    #[ORM\Column(length: 120)]
    private ?string $lastname = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $addressLine1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $addressLine2 = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 40, unique: true)]
    private string $inviteCode;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $ownershipTransferredAt = null;

    /** @var Collection<int, NannyAssignment> */
    #[ORM\OneToMany(targetEntity: NannyAssignment::class, mappedBy: 'nanny', orphanRemoval: true)]
    private Collection $assignments;

    /** @var Collection<int, Care> */
    #[ORM\OneToMany(targetEntity: Care::class, mappedBy: 'nanny')]
    private Collection $cares;

    /** @var Collection<int, MonthlyPayment> */
    #[ORM\OneToMany(targetEntity: MonthlyPayment::class, mappedBy: 'nanny')]
    private Collection $monthlyPayments;

    /** @var Collection<int, CareTemplate> */
    #[ORM\OneToMany(targetEntity: CareTemplate::class, mappedBy: 'nanny')]
    private Collection $careTemplates;

    /** @var Collection<int, NannyRate> */
    #[ORM\OneToMany(targetEntity: NannyRate::class, mappedBy: 'nanny')]
    private Collection $rates;

    public function __construct()
    {
        $this->publicId = UlidGenerator::generate();
        $this->inviteCode = UlidGenerator::generate();
        $this->createdAt = new \DateTime();
        $this->assignments = new ArrayCollection();
        $this->cares = new ArrayCollection();
        $this->monthlyPayments = new ArrayCollection();
        $this->careTemplates = new ArrayCollection();
        $this->rates = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getFullName();
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

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = trim($firstname);

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = trim($lastname);

        return $this;
    }

    public function getAddressLine1(): ?string
    {
        return $this->addressLine1;
    }

    public function setAddressLine1(?string $addressLine1): static
    {
        $addressLine1 = $addressLine1 !== null ? trim($addressLine1) : null;
        $this->addressLine1 = $addressLine1 !== '' ? $addressLine1 : null;

        return $this;
    }

    public function getAddressLine2(): ?string
    {
        return $this->addressLine2;
    }

    public function setAddressLine2(?string $addressLine2): static
    {
        $addressLine2 = $addressLine2 !== null ? trim($addressLine2) : null;
        $this->addressLine2 = $addressLine2 !== '' ? $addressLine2 : null;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $postalCode = $postalCode !== null ? trim($postalCode) : null;
        $this->postalCode = $postalCode !== '' ? $postalCode : null;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $city = $city !== null ? trim($city) : null;
        $this->city = $city !== '' ? $city : null;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $country = $country !== null ? trim($country) : null;
        $this->country = $country !== '' ? $country : null;

        return $this;
    }

    public function getInviteCode(): string
    {
        return $this->inviteCode;
    }

    public function regenerateInviteCode(): static
    {
        $this->inviteCode = UlidGenerator::generate();

        return $this;
    }

    public function setInviteCode(string $inviteCode): static
    {
        $this->inviteCode = $inviteCode;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

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

    public function getOwnershipTransferredAt(): ?\DateTimeInterface
    {
        return $this->ownershipTransferredAt;
    }

    public function setOwnershipTransferredAt(?\DateTimeInterface $ownershipTransferredAt): static
    {
        $this->ownershipTransferredAt = $ownershipTransferredAt;

        return $this;
    }

    public function getFullName(): string
    {
        return trim(($this->firstname ?? '') . ' ' . ($this->lastname ?? ''));
    }

    public function getPublicLabel(): string
    {
        $location = trim(implode(' ', array_filter([$this->postalCode, $this->city])));

        return $location !== '' ? sprintf('%s - %s', $this->getFullName(), $location) : $this->getFullName();
    }

    public function getAddressFingerprint(): string
    {
        return mb_strtolower(trim(implode('|', [
            $this->firstname,
            $this->lastname,
            $this->postalCode,
            $this->city,
        ])));
    }

    /** @return Collection<int, NannyAssignment> */
    public function getAssignments(): Collection
    {
        return $this->assignments;
    }

    public function isAssignedTo(User $user): bool
    {
        foreach ($this->assignments as $assignment) {
            if ($assignment->getUser() === $user && $assignment->getDetachedAt() === null) {
                return true;
            }
        }

        return false;
    }

    public function addAssignment(NannyAssignment $assignment): static
    {
        if (!$this->assignments->contains($assignment)) {
            $this->assignments->add($assignment);
            $assignment->setNanny($this);
        }

        return $this;
    }

    public function removeAssignment(NannyAssignment $assignment): static
    {
        $this->assignments->removeElement($assignment);

        return $this;
    }

    /** @return Collection<int, Care> */
    public function getCares(): Collection
    {
        return $this->cares;
    }

    /** @return Collection<int, MonthlyPayment> */
    public function getMonthlyPayments(): Collection
    {
        return $this->monthlyPayments;
    }

    /** @return Collection<int, CareTemplate> */
    public function getCareTemplates(): Collection
    {
        return $this->careTemplates;
    }

    /** @return Collection<int, NannyRate> */
    public function getRates(): Collection
    {
        return $this->rates;
    }
}
