<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add loan category on bank accounts (category applied to loan installments).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'This migration is generated for SQLite.');

        $this->addSql('ALTER TABLE bank_account ADD COLUMN loan_category_id INTEGER DEFAULT NULL REFERENCES bank_category (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_53A23E0AB687C2A6 ON bank_account (loan_category_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'This migration is generated for SQLite.');

        $this->addSql('DROP INDEX IDX_53A23E0AB687C2A6');
        $this->addSql('ALTER TABLE bank_account DROP COLUMN loan_category_id');
    }
}
