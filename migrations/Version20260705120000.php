<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add linked account on bank goals (current amount follows the account balance).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'This migration is generated for SQLite.');

        $this->addSql('ALTER TABLE bank_goal ADD COLUMN linked_account_id INTEGER DEFAULT NULL REFERENCES bank_account (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_56BCB9A0CAF84FD ON bank_goal (linked_account_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'This migration is generated for SQLite.');

        $this->addSql('DROP INDEX IDX_56BCB9A0CAF84FD');
        $this->addSql('ALTER TABLE bank_goal DROP COLUMN linked_account_id');
    }
}
