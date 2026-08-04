<?php

namespace App\Tests\Unit\Course;

use App\Entity\CourseProduct;
use App\Entity\CourseProductAlias;
use App\Entity\CourseStore;
use App\Entity\User;
use App\Repository\CourseProductAliasRepository;
use App\Repository\CourseProductRepository;
use App\Service\BankEncryptionService;
use App\Service\Course\CourseProductMatcher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Un meme libelle brut peut designer un produit different d'un magasin a l'autre : les alias appris
 * (App\Entity\CourseProductAlias) doivent donc etre segmentes par magasin, pas seulement par utilisateur.
 */
final class CourseProductMatcherTest extends TestCase
{
    private BankEncryptionService $encryption;

    protected function setUp(): void
    {
        $this->encryption = new BankEncryptionService(base64_encode(str_repeat('b', 32)));
    }

    public function testSameRawLabelResolvesToDifferentProductsInDifferentStores(): void
    {
        $user = new User();
        $storeA = new CourseStore();
        $storeB = new CourseStore();
        $productA = new CourseProduct();
        $productB = new CourseProduct();

        $aliasA = (new CourseProductAlias())->setStore($storeA)->setProduct($productA)->setNormalizedLabel('marque repere x');
        $aliasB = (new CourseProductAlias())->setStore($storeB)->setProduct($productB)->setNormalizedLabel('marque repere x');

        $aliases = $this->createMock(CourseProductAliasRepository::class);
        $aliases->method('findOneForUserStoreAndLabel')->willReturnCallback(
            static fn (User $forUser, CourseStore $store): ?CourseProductAlias => $store === $storeA ? $aliasA : ($store === $storeB ? $aliasB : null)
        );

        $matcher = new CourseProductMatcher($aliases, $this->createMock(CourseProductRepository::class), $this->encryption, $this->createMock(EntityManagerInterface::class));

        self::assertSame($productA, $matcher->match($user, 'Marque Repere X', [], $storeA));
        self::assertSame($productB, $matcher->match($user, 'Marque Repere X', [], $storeB));
    }

    public function testSkipsAliasLookupEntirelyWhenTheStoreIsUnknown(): void
    {
        $user = new User();
        $product = new CourseProduct();
        $product->setEncryptedName($this->encryption->encrypt('Lait demi ecreme') ?? '');

        $aliases = $this->createMock(CourseProductAliasRepository::class);
        $aliases->expects(self::never())->method('findOneForUserStoreAndLabel');

        $matcher = new CourseProductMatcher($aliases, $this->createMock(CourseProductRepository::class), $this->encryption, $this->createMock(EntityManagerInterface::class));
        $catalog = [['product' => $product, 'normalizedName' => 'lait demi ecreme']];

        // Sans magasin connu, seule la correspondance floue par nom de produit est tentee (pas d'alias).
        $result = $matcher->match($user, 'LAIT DEMI ECREME 1L', $catalog, null);

        self::assertSame($product, $result);
    }

    public function testLearnCreatesAStoreScopedAliasAndUpdatesItOnConflict(): void
    {
        $user = new User();
        $store = new CourseStore();
        $firstProduct = new CourseProduct();
        $secondProduct = new CourseProduct();

        $aliases = $this->createMock(CourseProductAliasRepository::class);
        $existing = (new CourseProductAlias())->setStore($store)->setProduct($firstProduct)->setNormalizedLabel('gelatine vahine');
        $aliases->method('findOneForUserStoreAndLabel')->willReturn($existing);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $matcher = new CourseProductMatcher($aliases, $this->createMock(CourseProductRepository::class), $this->encryption, $entityManager);
        $matcher->learn($user, 'Gelatine Vahine', $secondProduct, $store);

        self::assertSame($secondProduct, $existing->getProduct());
    }
}
