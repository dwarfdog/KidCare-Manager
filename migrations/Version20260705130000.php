<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add period attachment on recurring rules (income counted in its period or reserved for the next one).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'This migration is generated for SQLite.');

        $this->addSql("ALTER TABLE bank_recurring_rule ADD COLUMN period_attachment VARCHAR(16) DEFAULT 'current' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'This migration is generated for SQLite.');

        $this->addSql('ALTER TABLE bank_recurring_rule DROP COLUMN period_attachment');
    }
}
