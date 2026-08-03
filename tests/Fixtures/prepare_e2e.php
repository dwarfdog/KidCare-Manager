<?php

use App\Entity\BankAccount;
use App\Entity\BankCategory;
use App\Entity\BankTransaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');

final class E2eFixtureLoader extends KernelTestCase
{
    public static function load(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($entityManager);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);

        $user = (new User())
            ->setUsername('e2e-parent')
            ->setEmail('e2e@example.test')
            ->setEmailVerified(true);
        $hasher = $container->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, 'e2e-password'));
        $account = (new BankAccount())->setUser($user)->setEncryptedName('Compte E2E')->setType(BankAccount::TYPE_CURRENT);
        $category = (new BankCategory())->setUser($user)->setName('Tests E2E')->setType(BankCategory::TYPE_NEUTRAL);
        $entityManager->persist($user);
        $entityManager->persist($account);
        $entityManager->persist($category);

        for ($index = 0; $index < 55; ++$index) {
            $type = $index % 2 === 0 ? BankTransaction::TYPE_EXPENSE : BankTransaction::TYPE_INCOME;
            $transaction = (new BankTransaction())
                ->setUser($user)
                ->setAccount($account)
                ->setCategory($category)
                ->setDate((new \DateTimeImmutable('today'))->modify(sprintf('-%d days', $index)))
                ->setEncryptedLabel('Operation E2E ' . $index)
                ->setSignedAmount($index + 1, $type)
                ->setStatus(BankTransaction::STATUS_REAL);
            $entityManager->persist($transaction);
        }
        $entityManager->flush();

        file_put_contents(dirname(__DIR__, 2) . '/var/e2e-fixture.json', json_encode([
            'account' => $account->getPublicId(),
            'category' => $category->getPublicId(),
        ], JSON_THROW_ON_ERROR));
        self::ensureKernelShutdown();
    }
}

E2eFixtureLoader::load();
