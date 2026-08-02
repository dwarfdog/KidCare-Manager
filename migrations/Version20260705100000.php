<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add dated verification on bank transactions and migrate reconciled transactions.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'This migration is generated for SQLite.');

        $this->addSql('ALTER TABLE bank_transaction ADD COLUMN verified_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE bank_transaction SET verified_at = COALESCE(updated_at, created_at, CURRENT_TIMESTAMP), status = \'real\' WHERE status = \'reconciled\'');
        $this->addSql('CREATE INDEX IDX_BANK_TRANSACTION_USER_VERIFIED ON bank_transaction (user_id, verified_at)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'This migration is generated for SQLite.');

        $this->addSql('DROP INDEX IDX_BANK_TRANSACTION_USER_VERIFIED');
        $this->addSql('ALTER TABLE bank_transaction DROP COLUMN verified_at');
    }
}
