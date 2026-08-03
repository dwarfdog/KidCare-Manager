<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260803065719 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retire loan_initial_capital/loan_remaining_capital de bank_recurring_rule (jamais renseignes, le pret est modelise via BankAccount).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__bank_recurring_rule AS
            SELECT
              id,
              user_id,
              account_id,
              category_id,
              public_id,
              encrypted_label,
              amount,
              frequency,
              interval_days,
              start_date,
              end_date,
              is_active,
              created_at,
              updated_at,
              is_salary_reference,
              period_attachment
            FROM
              bank_recurring_rule
        SQL);
        $this->addSql('DROP TABLE bank_recurring_rule');
        $this->addSql(<<<'SQL'
            CREATE TABLE bank_recurring_rule (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              user_id INTEGER NOT NULL,
              account_id INTEGER NOT NULL,
              category_id INTEGER DEFAULT NULL,
              public_id VARCHAR(26) NOT NULL,
              encrypted_label VARCHAR(2048) NOT NULL,
              amount NUMERIC(12, 2) NOT NULL,
              frequency VARCHAR(24) NOT NULL,
              interval_days INTEGER DEFAULT NULL,
              start_date DATE NOT NULL,
              end_date DATE DEFAULT NULL,
              is_active BOOLEAN DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              is_salary_reference BOOLEAN DEFAULT 0 NOT NULL,
              period_attachment VARCHAR(16) DEFAULT 'current' NOT NULL,
              CONSTRAINT FK_BANK_RECURRING_USER FOREIGN KEY (user_id) REFERENCES user (id) ON
              UPDATE
                NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_BANK_RECURRING_ACCOUNT FOREIGN KEY (account_id) REFERENCES bank_account (id) ON
              UPDATE
                NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_BANK_RECURRING_CATEGORY FOREIGN KEY (category_id) REFERENCES bank_category (id) ON
              UPDATE
                NO ACTION ON DELETE
              SET
                NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO bank_recurring_rule (
              id, user_id, account_id, category_id,
              public_id, encrypted_label, amount,
              frequency, interval_days, start_date,
              end_date, is_active, created_at,
              updated_at, is_salary_reference,
              period_attachment
            )
            SELECT
              id,
              user_id,
              account_id,
              category_id,
              public_id,
              encrypted_label,
              amount,
              frequency,
              interval_days,
              start_date,
              end_date,
              is_active,
              created_at,
              updated_at,
              is_salary_reference,
              period_attachment
            FROM
              __temp__bank_recurring_rule
        SQL);
        $this->addSql('DROP TABLE __temp__bank_recurring_rule');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7E6D7D04B5B48B91 ON bank_recurring_rule (public_id)');
        $this->addSql('CREATE INDEX IDX_7E6D7D0412469DE2 ON bank_recurring_rule (category_id)');
        $this->addSql('CREATE INDEX IDX_7E6D7D049B6B5FBA ON bank_recurring_rule (account_id)');
        $this->addSql('CREATE INDEX IDX_7E6D7D04A76ED395 ON bank_recurring_rule (user_id)');
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_BANK_RECURRING_USER_ACTIVE_START ON bank_recurring_rule (user_id, is_active, start_date)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bank_recurring_rule ADD COLUMN loan_initial_capital NUMERIC(12, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE bank_recurring_rule ADD COLUMN loan_remaining_capital NUMERIC(12, 2) DEFAULT NULL');
    }
}
