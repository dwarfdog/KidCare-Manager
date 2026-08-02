<?php

namespace App\Entity;

use App\Repository\BankRecurringExceptionRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BankRecurringExceptionRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_BANK_RECURRING_EXCEPTION_DATE', fields: ['rule', 'exceptionDate'])]
class BankRecurringException
{
    public const ACTION_SKIP = 'skip';
    public const ACTION_MODIFY = 'modify';

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
    private ?BankRecurringRule $rule = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $exceptionDate;

    #[ORM\Column(length: 24)]
    private string $action = self::ACTION_SKIP;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $overrideDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $overrideAmount = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $overrideEncryptedLabel = null;

    public function __construct()
    {
        $this->publicId = UlidGenerator::generate();
        $this->exceptionDate = new \DateTime('today');
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

    public function getRule(): ?BankRecurringRule
    {
        return $this->rule;
    }

    public function setRule(?BankRecurringRule $rule): static
    {
        $this->rule = $rule;

        return $this;
    }

    public function getExceptionDate(): \DateTimeInterface
    {
        return $this->exceptionDate;
    }

    public function setExceptionDate(\DateTimeInterface $exceptionDate): static
    {
        $this->exceptionDate = $exceptionDate;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getOverrideDate(): ?\DateTimeInterface
    {
        return $this->overrideDate;
    }

    public function setOverrideDate(?\DateTimeInterface $overrideDate): static
    {
        $this->overrideDate = $overrideDate;

        return $this;
    }

    public function getOverrideAmount(): ?float
    {
        return $this->overrideAmount !== null ? (float) $this->overrideAmount : null;
    }

    public function setOverrideAmount(float|string|null $overrideAmount): static
    {
        $this->overrideAmount = $overrideAmount !== null && $overrideAmount !== ''
            ? number_format((float) $overrideAmount, 2, '.', '')
            : null;

        return $this;
    }

    public function getOverrideEncryptedLabel(): ?string
    {
        return $this->overrideEncryptedLabel;
    }

    public function setOverrideEncryptedLabel(?string $overrideEncryptedLabel): static
    {
        $this->overrideEncryptedLabel = $overrideEncryptedLabel;

        return $this;
    }
}
