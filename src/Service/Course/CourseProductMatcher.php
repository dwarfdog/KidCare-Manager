<?php

namespace App\Service\Course;

use App\Entity\CourseProduct;
use App\Entity\CourseProductAlias;
use App\Entity\CourseStore;
use App\Entity\CourseStoreAlias;
use App\Entity\User;
use App\Repository\CourseProductAliasRepository;
use App\Repository\CourseProductRepository;
use App\Service\BankEncryptionService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Auto-association d'une ligne de ticket a un App\Entity\CourseProduct du catalogue. Un meme produit
 * peut porter un libelle different d'un magasin a l'autre (ex: "GELATINE VAHINE ST 9 FEUILLES" chez
 * Super U) : l'alias appris (App\Entity\CourseProductAlias) est donc rattache au magasin du ticket, pas
 * seulement a l'utilisateur. Si le magasin du ticket n'est pas encore identifie, on ne tente aucune
 * correspondance par alias (qui serait par nature specifique a un magasin non determine) et on retombe
 * uniquement sur la correspondance floue par nom de produit. Meme esprit que
 * App\Service\BankCategoryRuleMatcher (apprentissage par confirmation utilisateur, pas de ML) : reste un
 * simple assistant, l'ecran de relecture systematique permet toujours a l'utilisateur de corriger/creer
 * le bon produit.
 */
final class CourseProductMatcher
{
    public function __construct(
        private readonly CourseProductAliasRepository $aliases,
        private readonly CourseProductRepository $products,
        private readonly BankEncryptionService $encryption,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array<int, array{product: CourseProduct, normalizedName: string}> */
    public function buildCatalog(User $user): array
    {
        $catalog = [];
        foreach ($this->products->findActiveForUser($user) as $product) {
            $catalog[] = [
                'product' => $product,
                'normalizedName' => CourseStoreAlias::normalize($this->encryption->decrypt($product->getEncryptedName())),
            ];
        }

        return $catalog;
    }

    /** @param array<int, array{product: CourseProduct, normalizedName: string}> $catalog */
    public function match(User $user, string $rawLabel, array $catalog, ?CourseStore $store): ?CourseProduct
    {
        $normalized = CourseStoreAlias::normalize($rawLabel);
        if ($normalized === '') {
            return null;
        }

        if ($store !== null) {
            $alias = $this->aliases->findOneForUserStoreAndLabel($user, $store, $normalized);
            if ($alias !== null) {
                return $alias->getProduct();
            }
        }

        foreach ($catalog as $entry) {
            if ($entry['normalizedName'] !== '' && $entry['normalizedName'] === $normalized) {
                return $entry['product'];
            }
        }

        $best = null;
        $bestLength = 0;
        foreach ($catalog as $entry) {
            $name = $entry['normalizedName'];
            if ($name === '' || mb_strlen($name) < 3) {
                continue;
            }
            if ((str_contains($normalized, $name) || str_contains($name, $normalized)) && mb_strlen($name) > $bestLength) {
                $best = $entry['product'];
                $bestLength = mb_strlen($name);
            }
        }

        return $best;
    }

    /**
     * Enregistre/actualise l'alias magasin+libelle -> produit afin qu'un futur ticket du meme magasin
     * portant le meme libelle brut soit auto-associe.
     */
    public function learn(User $user, string $rawLabel, CourseProduct $product, CourseStore $store): void
    {
        $normalized = CourseStoreAlias::normalize($rawLabel);
        if ($normalized === '') {
            return;
        }

        $existing = $this->aliases->findOneForUserStoreAndLabel($user, $store, $normalized);
        if ($existing !== null) {
            if ($existing->getProduct() !== $product) {
                $existing->setProduct($product);
            }

            return;
        }

        $alias = (new CourseProductAlias())
            ->setUser($user)
            ->setStore($store)
            ->setProduct($product)
            ->setNormalizedLabel($normalized);

        $this->entityManager->persist($alias);
    }
}
