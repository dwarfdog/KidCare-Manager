<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260627190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add loan monthly payment on bank_account and salary reference flag on bank_recurring_rule.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'This migration is generated for SQLite.');

        $this->addSql('ALTER TABLE bank_account ADD COLUMN loan_monthly_payment NUMERIC(12, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE bank_recurring_rule ADD COLUMN is_salary_reference BOOLEAN DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'This migration is generated for SQLite.');

        $this->addSql('ALTER TABLE bank_account DROP COLUMN loan_monthly_payment');
        $this->addSql('ALTER TABLE bank_recurring_rule DROP COLUMN is_salary_reference');
    }
}
