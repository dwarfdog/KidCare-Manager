<?php

namespace App\Tests\Functional;

use App\Entity\BankAccount;
use App\Entity\BankCategory;
use App\Entity\BankTransaction;
use App\Entity\MonthlyPayment;
use App\Entity\Nanny;
use App\Entity\NannyAssignment;
use App\Entity\User;
use App\Repository\BankCategoryRepository;
use App\Repository\BankTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BankExperienceTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testFocusLocatesTheCorrectPageAndVerificationKeepsFilters(): void
    {
        [$user, $account, $transactions] = $this->bankFixture(55);
        $this->client->loginUser($user);
        $target = $transactions[54];

        $crawler = $this->client->request('GET', '/bank/accounts/' . $account->getPublicId() . '?focus=' . $target->getPublicId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#bank-transaction-' . $target->getPublicId());
        self::assertSelectorTextContains('nav[aria-label="Pagination des operations"]', 'Page 3 / 3');

        $verificationPath = '/bank/transactions/' . $target->getPublicId() . '/verification';
        $token = (string) $crawler->filter(sprintf('form[action="%s"] input[name="_token"]', $verificationPath))->attr('value');
        $this->client->request('POST', '/bank/transactions/' . $target->getPublicId() . '/verification', [
            '_token' => $token,
            'verified' => '1',
            'return_context' => 'account',
            'return' => ['real_page' => '3', 'real_verified' => 'unverified', 'real_sort' => 'date', 'real_direction' => 'desc'],
        ]);

        self::assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString('real_verified=unverified', $location);
        self::assertStringContainsString('focus=' . $target->getPublicId(), $location);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'ne correspond plus aux filtres conserves');
    }

    public function testHomeDashboardDoesNotMixFamiliesSharingANanny(): void
    {
        $first = $this->user('first-family');
        $second = $this->user('second-family');
        $nanny = (new Nanny())->setFirstname('Nina')->setLastname('Partagee')->setCreatedBy($first);
        $this->entityManager->persist($nanny);
        foreach ([$first, $second] as $user) {
            $assignment = (new NannyAssignment())->setUser($user)->setNanny($nanny);
            $this->entityManager->persist($assignment);
        }
        $month = (new \DateTimeImmutable())->format('Y-m');
        $firstPayment = (new MonthlyPayment())->setUser($first)->setNanny($nanny)->setMonth($month)->setTotalsHours(2)->setTotalAmount(10);
        $secondPayment = (new MonthlyPayment())->setUser($second)->setNanny($nanny)->setMonth($month)->setTotalsHours(99)->setTotalAmount(999);
        $this->entityManager->persist($firstPayment);
        $this->entityManager->persist($secondPayment);
        $this->entityManager->flush();

        $this->client->loginUser($first);
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '10,00');
        self::assertSelectorTextNotContains('body', '999,00');
    }

    public function testPlannedFocusKeepsTheSalaryPeriodAndDisplaysAMessageWhenUnavailable(): void
    {
        [$user, $account] = $this->bankFixture(0);
        $transaction = (new BankTransaction())
            ->setUser($user)
            ->setAccount($account)
            ->setDate(new \DateTimeImmutable('tomorrow'))
            ->setEncryptedLabel('Operation prevue ciblee')
            ->setSignedAmount(25, BankTransaction::TYPE_EXPENSE)
            ->setStatus(BankTransaction::STATUS_PLANNED);
        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', sprintf('/bank/accounts/%s?focus=%s', $account->getPublicId(), $transaction->getPublicId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#bank-transaction-' . $transaction->getPublicId());
        self::assertSelectorTextContains('[role="status"]', 'aucune periode salariale');
    }

    public function testCategoryAnalyticsAggregatesSignedRealTransactionsOnly(): void
    {
        $user = $this->user('analytics-parent');
        $account = (new BankAccount())->setUser($user)->setEncryptedName('Compte analyse')->setType(BankAccount::TYPE_CURRENT);
        $category = (new BankCategory())->setUser($user)->setName('Categorie mixte')->setType(BankCategory::TYPE_NEUTRAL);
        $this->entityManager->persist($account);
        $this->entityManager->persist($category);
        foreach ([
            ['2026-01-05', 100, BankTransaction::TYPE_INCOME, BankTransaction::STATUS_REAL, false],
            ['2026-01-08', 20, BankTransaction::TYPE_EXPENSE, BankTransaction::STATUS_REAL, false],
            ['2026-02-01', 40, BankTransaction::TYPE_TRANSFER_IN, BankTransaction::STATUS_REAL, false],
            ['2026-02-02', 40, BankTransaction::TYPE_TRANSFER_OUT, BankTransaction::STATUS_REAL, false],
            ['2026-02-10', 999, BankTransaction::TYPE_INCOME, BankTransaction::STATUS_PLANNED, false],
            ['2026-02-11', 888, BankTransaction::TYPE_INCOME, BankTransaction::STATUS_REAL, true],
        ] as [$date, $amount, $type, $status, $archived]) {
            $transaction = (new BankTransaction())
                ->setUser($user)->setAccount($account)->setCategory($category)
                ->setDate(new \DateTimeImmutable($date))->setEncryptedLabel('Analyse')
                ->setSignedAmount($amount, $type)->setStatus($status);
            if ($archived) {
                $transaction->archive();
            }
            $this->entityManager->persist($transaction);
        }
        $this->entityManager->flush();

        /** @var BankTransactionRepository $transactions */
        $transactions = static::getContainer()->get(BankTransactionRepository::class);
        self::assertSame([
            ['month' => '2026-01', 'gains' => 100.0, 'expenses' => 20.0],
            ['month' => '2026-02', 'gains' => 40.0, 'expenses' => 40.0],
        ], $transactions->monthlyEvolutionForCategory($user, $category, [$account], new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-02-28')));

        /** @var BankCategoryRepository $categories */
        $categories = static::getContainer()->get(BankCategoryRepository::class);
        self::assertSame([$category->getPublicId()], array_map(static fn (BankCategory $item): string => $item->getPublicId(), $categories->findActiveWithRealDataForUser($user)));

        $this->client->loginUser($user);
        $query = [
            'category' => $category->getPublicId(),
            'accounts' => [$account->getPublicId()],
            'period' => 'custom',
            'from' => '2026-01-01',
            'to' => '2026-02-28',
        ];
        $this->client->request('GET', '/bank/analytics/data', $query);
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNull($payload['error']);
        self::assertSame([100, 40], $payload['data']['gains']);
        self::assertSame([20, 40], $payload['data']['expenses']);
        self::assertSame([80, 0], $payload['data']['balance']);

        $this->client->request('GET', '/bank/analytics', $query);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-bank-category-evolution-target="table"]', '100,00 €');
    }

    /** @return array{User, BankAccount, BankTransaction[]} */
    private function bankFixture(int $count): array
    {
        $user = $this->user('bank-parent');
        $account = (new BankAccount())->setUser($user)->setEncryptedName('Compte test')->setType(BankAccount::TYPE_CURRENT);
        $category = (new BankCategory())->setUser($user)->setName('Courses')->setType(BankCategory::TYPE_EXPENSE);
        $this->entityManager->persist($account);
        $this->entityManager->persist($category);
        $transactions = [];
        for ($index = 0; $index < $count; ++$index) {
            $transaction = (new BankTransaction())
                ->setUser($user)
                ->setAccount($account)
                ->setCategory($category)
                ->setDate((new \DateTimeImmutable('today'))->modify(sprintf('-%d days', $index)))
                ->setEncryptedLabel('Operation ' . $index)
                ->setSignedAmount($index + 1, BankTransaction::TYPE_EXPENSE)
                ->setStatus(BankTransaction::STATUS_REAL);
            $this->entityManager->persist($transaction);
            $transactions[] = $transaction;
        }
        $this->entityManager->flush();

        return [$user, $account, $transactions];
    }

    private function user(string $username): User
    {
        $user = (new User())->setUsername($username)->setPassword('test-password')->setEmail($username . '@example.test')->setEmailVerified(true);
        $this->entityManager->persist($user);

        return $user;
    }
}
