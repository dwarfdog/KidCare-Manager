<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260627200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add loan payment day and debit account on bank_account, and loan link on bank_transaction.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'This migration is generated for SQLite.');

        $this->addSql('ALTER TABLE bank_account ADD COLUMN loan_payment_day INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE bank_account ADD COLUMN loan_debit_account_id INTEGER DEFAULT NULL REFERENCES bank_account (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_53A23E0A44572A4D ON bank_account (loan_debit_account_id)');

        $this->addSql('ALTER TABLE bank_transaction ADD COLUMN loan_account_id INTEGER DEFAULT NULL REFERENCES bank_account (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_50BCB3AE8BF076DC ON bank_transaction (loan_account_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'This migration is generated for SQLite.');

        $this->addSql('DROP INDEX IDX_50BCB3AE8BF076DC');
        $this->addSql('ALTER TABLE bank_transaction DROP COLUMN loan_account_id');
        $this->addSql('DROP INDEX IDX_53A23E0A44572A4D');
        $this->addSql('ALTER TABLE bank_account DROP COLUMN loan_debit_account_id');
        $this->addSql('ALTER TABLE bank_account DROP COLUMN loan_payment_day');
    }
}
