<?php

namespace App\Service\Course;

use App\Entity\CourseStore;
use App\Entity\CourseStoreAlias;
use App\Entity\User;
use App\Repository\CourseStoreAliasRepository;
use App\Repository\CourseStoreRepository;
use App\Service\BankEncryptionService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Auto-suggestion du magasin a partir de l'en-tete detecte sur un ticket, via les alias appris
 * (App\Entity\CourseStoreAlias) puis une correspondance floue "contient" sur le nom des magasins actifs.
 * Meme esprit que App\Service\Course\CourseProductMatcher.
 */
final class CourseStoreMatcher
{
    public function __construct(
        private readonly CourseStoreAliasRepository $aliases,
        private readonly CourseStoreRepository $stores,
        private readonly BankEncryptionService $encryption,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function match(User $user, ?string $storeNameGuess): ?CourseStore
    {
        if ($storeNameGuess === null || trim($storeNameGuess) === '') {
            return null;
        }

        $normalized = CourseStoreAlias::normalize($storeNameGuess);
        $alias = $this->aliases->findOneForUserByLabel($user, $normalized);
        if ($alias !== null) {
            return $alias->getStore();
        }

        $best = null;
        $bestLength = 0;
        foreach ($this->stores->findActiveForUser($user) as $store) {
            $name = CourseStoreAlias::normalize($this->encryption->decrypt($store->getEncryptedName()));
            if ($name === '' || mb_strlen($name) < 3) {
                continue;
            }
            if ((str_contains($normalized, $name) || str_contains($name, $normalized)) && mb_strlen($name) > $bestLength) {
                $best = $store;
                $bestLength = mb_strlen($name);
            }
        }

        return $best;
    }

    public function learn(User $user, string $storeNameGuess, CourseStore $store): void
    {
        $normalized = CourseStoreAlias::normalize($storeNameGuess);
        if ($normalized === '') {
            return;
        }

        $existing = $this->aliases->findOneForUserByLabel($user, $normalized);
        if ($existing !== null) {
            if ($existing->getStore() !== $store) {
                $existing->setStore($store);
            }

            return;
        }

        $alias = (new CourseStoreAlias())
            ->setUser($user)
            ->setStore($store)
            ->setNormalizedLabel($normalized);

        $this->entityManager->persist($alias);
    }
}
