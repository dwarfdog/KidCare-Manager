<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260627170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Bank Manager accounts, transactions, recurrence, budgets, goals, imports, attachments and audit tables.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'This migration is generated for SQLite.');

        $this->addSql('CREATE TABLE bank_account (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, public_id VARCHAR(26) NOT NULL, encrypted_name VARCHAR(1024) NOT NULL, type VARCHAR(32) NOT NULL, initial_balance NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, initial_balance_date DATE NOT NULL, encrypted_iban VARCHAR(1024) DEFAULT NULL, loan_initial_capital NUMERIC(12, 2) DEFAULT NULL, loan_remaining_capital NUMERIC(12, 2) DEFAULT NULL, loan_annual_rate NUMERIC(6, 3) DEFAULT NULL, loan_end_date DATE DEFAULT NULL, archived_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_BANK_ACCOUNT_USER FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_53A23E0AB5B48B91 ON bank_account (public_id)');
        $this->addSql('CREATE INDEX IDX_BANK_ACCOUNT_USER_ARCHIVE ON bank_account (user_id, archived_at)');
        $this->addSql('CREATE INDEX IDX_53A23E0AA76ED395 ON bank_account (user_id)');

        $this->addSql('CREATE TABLE bank_category (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, public_id VARCHAR(26) NOT NULL, name VARCHAR(120) NOT NULL, type VARCHAR(24) NOT NULL, color VARCHAR(24) NOT NULL, icon VARCHAR(60) NOT NULL, is_default BOOLEAN DEFAULT 0 NOT NULL, archived_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_BANK_CATEGORY_USER FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_370C0346B5B48B91 ON bank_category (public_id)');
        $this->addSql('CREATE INDEX IDX_BANK_CATEGORY_USER_TYPE_ARCHIVE ON bank_category (user_id, type, archived_at)');
        $this->addSql('CREATE INDEX IDX_370C0346A76ED395 ON bank_category (user_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE bank_import_profile (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                user_id INTEGER NOT NULL,
                account_id INTEGER DEFAULT NULL,
                public_id VARCHAR(26) NOT NULL,
                name VARCHAR(120) NOT NULL,
                delimiter VARCHAR(8) NOT NULL,
                date_format VARCHAR(30) NOT NULL,
                mapping CLOB NOT NULL --(DC2Type:json)
                ,
                created_at DATETIME NOT NULL,
                CONSTRAINT FK_BANK_IMPORT_PROFILE_USER FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_BANK_IMPORT_PROFILE_ACCOUNT FOREIGN KEY (account_id) REFERENCES bank_account (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_51F7F3AB5B48B91 ON bank_import_profile (public_id)');
        $this->addSql('CREATE INDEX IDX_BANK_IMPORT_PROFILE_USER_ACCOUNT ON bank_import_profile (user_id, account_id)');
        $this->addSql('CREATE INDEX IDX_51F7F3AA76ED395 ON bank_import_profile (user_id)');
        $this->addSql('CREATE INDEX IDX_51F7F3A9B6B5FBA ON bank_import_profile (account_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE bank_import_batch (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                user_id INTEGER NOT NULL,
                account_id INTEGER NOT NULL,
                profile_id INTEGER DEFAULT NULL,
                public_id VARCHAR(26) NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                imported_count INTEGER DEFAULT 0 NOT NULL,
                skipped_count INTEGER DEFAULT 0 NOT NULL,
                error_count INTEGER DEFAULT 0 NOT NULL,
                errors CLOB NOT NULL --(DC2Type:json)
                ,
                created_at DATETIME NOT NULL,
                CONSTRAINT FK_BANK_IMPORT_BATCH_USER FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_BANK_IMPORT_BATCH_ACCOUNT FOREIGN KEY (account_id) REFERENCES bank_account (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_BANK_IMPORT_BATCH_PROFILE FOREIGN KEY (profile_id) REFERENCES bank_import_profile (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D5C61A01B5B48B91 ON bank_import_batch (public_id)');
        $this->addSql('CREATE INDEX IDX_BANK_IMPORT_BATCH_USER_DATE ON bank_import_batch (user_id, created_at)');
        $this->addSql('CREATE INDEX IDX_D5C61A01A76ED395 ON bank_import_batch (user_id)');
        $this->addSql('CREATE INDEX IDX_D5C61A019B6B5FBA ON bank_import_batch (account_id)');
        $this->addSql('CREATE INDEX IDX_D5C61A01CCFA12B8 ON bank_import_batch (profile_id)');

        $this->addSql('CREATE TABLE bank_recurring_rule (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, account_id INTEGER NOT NULL, category_id INTEGER DEFAULT NULL, public_id VARCHAR(26) NOT NULL, encrypted_label VARCHAR(2048) NOT NULL, amount NUMERIC(12, 2) NOT NULL, frequency VARCHAR(24) NOT NULL, interval_days INTEGER DEFAULT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, is_active BOOLEAN DEFAULT 1 NOT NULL, loan_initial_capital NUMERIC(12, 2) DEFAULT NULL, loan_remaining_capital NUMERIC(12, 2) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_BANK_RECURRING_USER FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BANK_RECURRING_ACCOUNT FOREIGN KEY (account_id) REFERENCES bank_account (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BANK_RECURRING_CATEGORY FOREIGN KEY (category_id) REFERENCES bank_category (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7E6D7D04B5B48B91 ON bank_recurring_rule (public_id)');
        $this->addSql('CREATE INDEX IDX_BANK_RECURRING_USER_ACTIVE_START ON bank_recurring_rule (user_id, is_active, start_date)');
        $this->addSql('CREATE INDEX IDX_7E6D7D04A76ED395 ON bank_recurring_rule (user_id)');
        $this->addSql('CREATE INDEX IDX_7E6D7D049B6B5FBA ON bank_recurring_rule (account_id)');
        $this->addSql('CREATE INDEX IDX_7E6D7D0412469DE2 ON bank_recurring_rule (category_id)');

        $this->addSql('CREATE TABLE bank_transaction (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, account_id INTEGER NOT NULL, category_id INTEGER DEFAULT NULL, import_batch_id INTEGER DEFAULT NULL, recurring_rule_id INTEGER DEFAULT NULL, monthly_payment_id INTEGER DEFAULT NULL, mirror_transaction_id INTEGER DEFAULT NULL, public_id VARCHAR(26) NOT NULL, transaction_date DATE NOT NULL, encrypted_label VARCHAR(2048) NOT NULL, amount NUMERIC(12, 2) NOT NULL, type VARCHAR(24) NOT NULL, status VARCHAR(24) NOT NULL, source VARCHAR(24) NOT NULL, import_hash VARCHAR(64) DEFAULT NULL, transfer_group VARCHAR(26) DEFAULT NULL, archived_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_BANK_TRANSACTION_USER FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BANK_TRANSACTION_ACCOUNT FOREIGN KEY (account_id) REFERENCES bank_account (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BANK_TRANSACTION_CATEGORY FOREIGN KEY (category_id) REFERENCES bank_category (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BANK_TRANSACTION_IMPORT_BATCH FOREIGN KEY (import_batch_id) REFERENCES bank_import_batch (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BANK_TRANSACTION_RECURRING FOREIGN KEY (recurring_rule_id) REFERENCES bank_recurring_rule (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BANK_TRANSACTION_PAYMENT FOREIGN KEY (monthly_payment_id) REFERENCES monthly_payment (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BANK_TRANSACTION_MIRROR FOREIGN KEY (mirror_transaction_id) REFERENCES bank_transaction (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_50BCB3AEB5B48B91 ON bank_transaction (public_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BANK_TRANSACTION_IMPORT_HASH ON bank_transaction (user_id, account_id, import_hash)');
        $this->addSql('CREATE INDEX IDX_BANK_TRANSACTION_USER_ACCOUNT_DATE ON bank_transaction (user_id, account_id, transaction_date)');
        $this->addSql('CREATE INDEX IDX_BANK_TRANSACTION_USER_CATEGORY_DATE ON bank_transaction (user_id, category_id, transaction_date)');
        $this->addSql('CREATE INDEX IDX_BANK_TRANSACTION_USER_STATUS_DATE ON bank_transaction (user_id, status, transaction_date)');
        $this->addSql('CREATE INDEX IDX_50BCB3AEA76ED395 ON bank_transaction (user_id)');
        $this->addSql('CREATE INDEX IDX_50BCB3AE9B6B5FBA ON bank_transaction (account_id)');
        $this->addSql('CREATE INDEX IDX_50BCB3AE12469DE2 ON bank_transaction (category_id)');
        $this->addSql('CREATE INDEX IDX_50BCB3AE5A310080 ON bank_transaction (import_batch_id)');
        $this->addSql('CREATE INDEX IDX_50BCB3AEACB896D8 ON bank_transaction (recurring_rule_id)');
        $this->addSql('CREATE INDEX IDX_50BCB3AECDDEC33E ON bank_transaction (monthly_payment_id)');
        $this->addSql('CREATE INDEX IDX_50BCB3AE544901D2 ON bank_transaction (mirror_transaction_id)');

        $this->addSql('CREATE TABLE bank_recurring_exception (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, rule_id INTEGER NOT NULL, public_id VARCHAR(26) NOT NULL, exception_date DATE NOT NULL, "action" VARCHAR(24) NOT NULL, override_date DATE DEFAULT NULL, override_amount NUMERIC(12, 2) DEFAULT NULL, override_encrypted_label VARCHAR(2048) DEFAULT NULL, CONSTRAINT FK_BANK_RECURRING_EXCEPTION_USER FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BANK_RECURRING_EXCEPTION_RULE FOREIGN KEY (rule_id) REFERENCES bank_recurring_rule (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EC4E738BB5B48B91 ON bank_recurring_exception (public_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BANK_RECURRING_EXCEPTION_DATE ON bank_recurring_exception (rule_id, exception_date)');
        $this->addSql('CREATE INDEX IDX_EC4E738BA76ED395 ON bank_recurring_exception (user_id)');
        $this->addSql('CREATE INDEX IDX_EC4E738B744E0351 ON bank_recurring_exception (rule_id)');

        $this->addSql('CREATE TABLE bank_budget (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, category_id INTEGER NOT NULL, public_id VARCHAR(26) NOT NULL, period_type VARCHAR(20) NOT NULL, period_start DATE NOT NULL, period_end DATE NOT NULL, amount NUMERIC(12, 2) NOT NULL, archived_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_BANK_BUDGET_USER FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BANK_BUDGET_CATEGORY FOREIGN KEY (category_id) REFERENCES bank_category (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5397D806B5B48B91 ON bank_budget (public_id)');
        $this->addSql('CREATE INDEX IDX_BANK_BUDGET_USER_PERIOD ON bank_budget (user_id, period_type, period_start)');
        $this->addSql('CREATE INDEX IDX_5397D806A76ED395 ON bank_budget (user_id)');
        $this->addSql('CREATE INDEX IDX_5397D80612469DE2 ON bank_budget (category_id)');

        $this->addSql('CREATE TABLE bank_goal (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, public_id VARCHAR(26) NOT NULL, name VARCHAR(140) NOT NULL, target_amount NUMERIC(12, 2) NOT NULL, current_amount NUMERIC(12, 2) NOT NULL, target_date DATE DEFAULT NULL, status VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_BANK_GOAL_USER FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_56BCB9A0B5B48B91 ON bank_goal (public_id)');
        $this->addSql('CREATE INDEX IDX_BANK_GOAL_USER_STATUS ON bank_goal (user_id, status)');
        $this->addSql('CREATE INDEX IDX_56BCB9A0A76ED395 ON bank_goal (user_id)');

        $this->addSql('CREATE TABLE bank_attachment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, transaction_id INTEGER NOT NULL, public_id VARCHAR(26) NOT NULL, encrypted_original_name VARCHAR(1024) NOT NULL, mime_type VARCHAR(120) NOT NULL, size_bytes INTEGER NOT NULL, content BLOB NOT NULL, content_hash VARCHAR(64) NOT NULL, archived_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_BANK_ATTACHMENT_USER FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BANK_ATTACHMENT_TRANSACTION FOREIGN KEY (transaction_id) REFERENCES bank_transaction (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F0FF1F0DB5B48B91 ON bank_attachment (public_id)');
        $this->addSql('CREATE INDEX IDX_BANK_ATTACHMENT_USER_TRANSACTION ON bank_attachment (user_id, transaction_id)');
        $this->addSql('CREATE INDEX IDX_F0FF1F0DA76ED395 ON bank_attachment (user_id)');
        $this->addSql('CREATE INDEX IDX_F0FF1F0D2FC0CB0F ON bank_attachment (transaction_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE bank_audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                user_id INTEGER DEFAULT NULL,
                public_id VARCHAR(26) NOT NULL,
                actor_identifier VARCHAR(180) DEFAULT NULL,
                "action" VARCHAR(80) NOT NULL,
                entity_type VARCHAR(80) NOT NULL,
                entity_public_id VARCHAR(40) DEFAULT NULL,
                context CLOB NOT NULL --(DC2Type:json)
                ,
                created_at DATETIME NOT NULL,
                CONSTRAINT FK_BANK_AUDIT_USER FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_850C966CB5B48B91 ON bank_audit_log (public_id)');
        $this->addSql('CREATE INDEX IDX_BANK_AUDIT_USER_DATE ON bank_audit_log (user_id, created_at)');
        $this->addSql('CREATE INDEX IDX_BANK_AUDIT_ENTITY ON bank_audit_log (entity_type, entity_public_id)');
        $this->addSql('CREATE INDEX IDX_850C966CA76ED395 ON bank_audit_log (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'This migration is generated for SQLite.');

        $this->addSql('DROP TABLE bank_audit_log');
        $this->addSql('DROP TABLE bank_attachment');
        $this->addSql('DROP TABLE bank_goal');
        $this->addSql('DROP TABLE bank_budget');
        $this->addSql('DROP TABLE bank_recurring_exception');
        $this->addSql('DROP TABLE bank_transaction');
        $this->addSql('DROP TABLE bank_recurring_rule');
        $this->addSql('DROP TABLE bank_import_batch');
        $this->addSql('DROP TABLE bank_import_profile');
        $this->addSql('DROP TABLE bank_category');
        $this->addSql('DROP TABLE bank_account');
    }
}
