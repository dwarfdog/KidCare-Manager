<?php

namespace App\Service\Course;

use App\Entity\CourseCategory;
use App\Entity\User;
use App\Repository\BankCategoryRepository;
use App\Repository\CourseCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Seede les sous-categories de courses par defaut, chacune rattachee a une App\Entity\BankCategory
 * parente existante (toutes vers "Alimentation" au depart, l'utilisateur peut ensuite reassigner
 * chaque sous-categorie a une autre categorie Bank depuis l'ecran de gestion des categories Courses).
 * Clone de App\Service\BankCategorySeeder.
 */
final class CourseCategorySeeder
{
    private const DEFAULTS = [
        ['Fruits & Legumes', '#65a30d', 'apple-whole'],
        ['Viandes & Poissons', '#dc2626', 'drumstick-bite'],
        ['Cremerie & Produits laitiers', '#0891b2', 'cheese'],
        ['Epicerie salee', '#ea580c', 'jar'],
        ['Epicerie sucree', '#d97706', 'cookie'],
        ['Boissons', '#2563eb', 'bottle-water'],
        ['Surgeles', '#0ea5e9', 'snowflake'],
        ['Hygiene & Beaute', '#db2777', 'pump-soap'],
        ['Entretien & Maison', '#475569', 'spray-can-sparkles'],
        ['Bebe', '#f472b6', 'baby'],
        ['Animalerie', '#a16207', 'paw'],
        ['Autres', '#64748b', 'shopping-basket'],
    ];

    private const PARENT_BANK_CATEGORY = 'Alimentation';

    public function __construct(
        private readonly CourseCategoryRepository $categoryRepository,
        private readonly BankCategoryRepository $bankCategoryRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function ensureForUser(User $user): void
    {
        if ($this->categoryRepository->countForUser($user) > 0) {
            return;
        }

        $parent = $this->bankCategoryRepository->findOneByName($user, self::PARENT_BANK_CATEGORY);
        if ($parent === null) {
            return;
        }

        foreach (self::DEFAULTS as [$name, $color, $icon]) {
            $category = (new CourseCategory())
                ->setUser($user)
                ->setName($name)
                ->setParentCategory($parent)
                ->setColor($color)
                ->setIcon($icon)
                ->setDefault(true);

            $this->entityManager->persist($category);
        }

        $this->entityManager->flush();
    }
}
