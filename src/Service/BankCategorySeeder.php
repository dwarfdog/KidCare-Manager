<?php

namespace App\Service;

use App\Entity\BankCategory;
use App\Entity\User;
use App\Repository\BankCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

final class BankCategorySeeder
{
    private const DEFAULTS = [
        ['Salaire', BankCategory::TYPE_INCOME, '#16a34a', 'briefcase'],
        ['Allocations', BankCategory::TYPE_INCOME, '#0d9488', 'hand-coins'],
        ['Logement', BankCategory::TYPE_EXPENSE, '#2563eb', 'home'],
        ['Alimentation', BankCategory::TYPE_EXPENSE, '#ea580c', 'shopping-basket'],
        ['Transport', BankCategory::TYPE_EXPENSE, '#7c3aed', 'car'],
        ['Sante', BankCategory::TYPE_EXPENSE, '#dc2626', 'heart-pulse'],
        ['Enfants', BankCategory::TYPE_EXPENSE, '#db2777', 'baby'],
        ['Loisirs', BankCategory::TYPE_EXPENSE, '#9333ea', 'ticket'],
        ['Epargne', BankCategory::TYPE_NEUTRAL, '#0891b2', 'piggy-bank'],
        ['Impots', BankCategory::TYPE_EXPENSE, '#475569', 'landmark'],
        ['Frais bancaires', BankCategory::TYPE_EXPENSE, '#b45309', 'receipt'],
        ['Nounou', BankCategory::TYPE_EXPENSE, '#0f766e', 'calendar-check'],
        ['Virement interne', BankCategory::TYPE_TRANSFER, '#64748b', 'arrow-left-right'],
    ];

    public function __construct(
        private readonly BankCategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function ensureForUser(User $user): void
    {
        if ($this->categoryRepository->countForUser($user) > 0) {
            return;
        }

        foreach (self::DEFAULTS as [$name, $type, $color, $icon]) {
            $category = (new BankCategory())
                ->setUser($user)
                ->setName($name)
                ->setType($type)
                ->setColor($color)
                ->setIcon($icon)
                ->setDefault(true);

            $this->entityManager->persist($category);
        }

        $this->entityManager->flush();
    }
}
