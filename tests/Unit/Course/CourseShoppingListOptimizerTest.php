<?php

namespace App\Tests\Unit\Course;

use App\Entity\CourseProduct;
use App\Entity\CourseReceipt;
use App\Entity\CourseReceiptLine;
use App\Entity\CourseShoppingList;
use App\Entity\CourseShoppingListItem;
use App\Entity\CourseStore;
use App\Entity\User;
use App\Repository\CourseReceiptLineRepository;
use App\Service\BankEncryptionService;
use App\Service\Course\CourseProductPriceAnalyticsService;
use App\Service\Course\CourseShoppingListOptimizer;
use PHPUnit\Framework\TestCase;

/**
 * App\Service\Course\CourseProductPriceAnalyticsService et App\Service\Course\CourseShoppingListOptimizer
 * sont des classes "final" (comme le reste du code metier de l'application) : au lieu de les mocker
 * (impossible avec PHPUnit sur une classe finale), on construit des instances reelles au-dessus d'un
 * repository mocke, exactement comme App\Service\BankEncryptionService l'est deja dans
 * tests/Unit/KidCareBankBridgeTest.php et tests/Unit/BankAccountManagerTest.php.
 */
final class CourseShoppingListOptimizerTest extends TestCase
{
    private BankEncryptionService $encryption;

    protected function setUp(): void
    {
        $this->encryption = new BankEncryptionService(base64_encode(str_repeat('b', 32)));
    }

    public function testBestSingleStorePrefersTheStoreCoveringTheMostItemsThenCheapest(): void
    {
        $user = new User();
        $storeA = $this->store('Magasin A');
        $storeB = $this->store('Magasin B');
        $milk = $this->product('Lait');
        $bread = $this->product('Pain');

        $lineRepository = $this->createMock(CourseReceiptLineRepository::class);
        $lineRepository->method('findConfirmedForProduct')->willReturnCallback(function (User $forUser, CourseProduct $product) use ($milk, $bread, $storeA, $storeB) {
            if ($product === $milk) {
                return [
                    $this->line($storeA, '2026-01-01', 1.0, 1.0),
                    $this->line($storeB, '2026-01-02', 1.0, 0.8),
                ];
            }
            if ($product === $bread) {
                // Le pain n'est connu que dans le magasin A : le magasin B ne couvre pas tous les articles.
                return [$this->line($storeA, '2026-01-01', 1.0, 2.0)];
            }

            return [];
        });

        $optimizer = new CourseShoppingListOptimizer(new CourseProductPriceAnalyticsService($lineRepository, $this->encryption), $this->encryption);

        $list = (new CourseShoppingList())->setUser($user);
        $list->addItem((new CourseShoppingListItem())->setProduct($milk)->setDesiredQuantity(1));
        $list->addItem((new CourseShoppingListItem())->setProduct($bread)->setDesiredQuantity(1));

        $result = $optimizer->bestSingleStore($user, $list);

        self::assertSame('Magasin A', $result['stores'][0]['storeName']);
        self::assertSame(2, $result['stores'][0]['coveredCount']);
        self::assertSame(3.0, $result['stores'][0]['total']);
        self::assertSame('Magasin B', $result['stores'][1]['storeName']);
        self::assertSame(1, $result['stores'][1]['coveredCount']);
        self::assertSame(1, $result['stores'][1]['missingCount']);
    }

    public function testMultiStoreSplitAssignsEachItemToItsCheapestKnownStore(): void
    {
        $user = new User();
        $storeA = $this->store('Magasin A');
        $storeB = $this->store('Magasin B');
        $milk = $this->product('Lait');

        $lineRepository = $this->createMock(CourseReceiptLineRepository::class);
        $lineRepository->method('findConfirmedForProduct')->willReturn([
            $this->line($storeA, '2026-01-01', 1.0, 1.0),
            $this->line($storeB, '2026-01-02', 1.0, 0.8),
        ]);

        $optimizer = new CourseShoppingListOptimizer(new CourseProductPriceAnalyticsService($lineRepository, $this->encryption), $this->encryption);

        $list = (new CourseShoppingList())->setUser($user);
        $list->addItem((new CourseShoppingListItem())->setProduct($milk)->setDesiredQuantity(3));

        $result = $optimizer->multiStoreSplit($user, $list);

        self::assertCount(1, $result['groups']);
        self::assertSame('Magasin B', $result['groups'][0]['storeName']);
        self::assertSame(2.4, $result['groups'][0]['subtotal']);
        self::assertSame(2.4, $result['grandTotal']);
        self::assertSame([], $result['unknownItems']);
    }

    public function testMultiStoreSplitReportsItemsWithNoPriceHistory(): void
    {
        $user = new User();
        $unknownProduct = $this->product('Produit inconnu');

        $lineRepository = $this->createMock(CourseReceiptLineRepository::class);
        $lineRepository->method('findConfirmedForProduct')->willReturn([]);

        $optimizer = new CourseShoppingListOptimizer(new CourseProductPriceAnalyticsService($lineRepository, $this->encryption), $this->encryption);

        $list = (new CourseShoppingList())->setUser($user);
        $list->addItem((new CourseShoppingListItem())->setProduct($unknownProduct)->setDesiredQuantity(1));

        $result = $optimizer->multiStoreSplit($user, $list);

        self::assertSame([], $result['groups']);
        self::assertSame(0.0, $result['grandTotal']);
        self::assertSame(['Produit inconnu'], $result['unknownItems']);
    }

    private function store(string $name): CourseStore
    {
        return (new CourseStore())->setEncryptedName($this->encryption->encrypt($name) ?? '');
    }

    private function product(string $name): CourseProduct
    {
        return (new CourseProduct())->setEncryptedName($this->encryption->encrypt($name) ?? '');
    }

    private function line(CourseStore $store, string $date, float $quantity, float $unitPrice): CourseReceiptLine
    {
        $receipt = (new CourseReceipt())->setStore($store)->setPurchaseDate(new \DateTimeImmutable($date));

        return (new CourseReceiptLine())
            ->setReceipt($receipt)
            ->setQuantity($quantity)
            ->setUnit(CourseProduct::UNIT_PIECE)
            ->setUnitPrice($unitPrice)
            ->setLineTotal(round($quantity * $unitPrice, 2));
    }
}
