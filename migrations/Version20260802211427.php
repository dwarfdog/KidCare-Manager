<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802211427 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add complete KidCare and Bank Manager improvements: care lifecycle, private documents, notifications, splits, rules, reconciliation, simulations, net worth, alerts and dashboard preferences.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE bank_alert_setting (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              user_id INTEGER NOT NULL,
              public_id VARCHAR(26) NOT NULL,
              type VARCHAR(32) NOT NULL,
              target_type VARCHAR(20) NOT NULL,
              target_public_id VARCHAR(26) DEFAULT NULL,
              threshold NUMERIC(12, 2) DEFAULT NULL,
              active BOOLEAN DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              CONSTRAINT FK_8B824E51A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8B824E51B5B48B91 ON bank_alert_setting (public_id)');
        $this->addSql('CREATE INDEX IDX_8B824E51A76ED395 ON bank_alert_setting (user_id)');
        $this->addSql('CREATE INDEX IDX_ALERT_TARGET ON bank_alert_setting (user_id, target_type, target_public_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE bank_analytics_favorite (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              user_id INTEGER NOT NULL,
              public_id VARCHAR(26) NOT NULL,
              name VARCHAR(120) NOT NULL,
              filters CLOB NOT NULL --(DC2Type:json)
              ,
              created_at DATETIME NOT NULL,
              CONSTRAINT FK_28D445D2A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_28D445D2B5B48B91 ON bank_analytics_favorite (public_id)');
        $this->addSql('CREATE INDEX IDX_28D445D2A76ED395 ON bank_analytics_favorite (user_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE bank_asset (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              user_id INTEGER NOT NULL,
              public_id VARCHAR(26) NOT NULL,
              name VARCHAR(120) NOT NULL,
              type VARCHAR(16) NOT NULL,
              current_value NUMERIC(14, 2) NOT NULL,
              archived_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              CONSTRAINT FK_8059429A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8059429B5B48B91 ON bank_asset (public_id)');
        $this->addSql('CREATE INDEX IDX_8059429A76ED395 ON bank_asset (user_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE bank_asset_valuation (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              asset_id INTEGER NOT NULL,
              value NUMERIC(14, 2) NOT NULL,
              valued_at DATE NOT NULL,
              CONSTRAINT FK_33CCCCAF5DA1941 FOREIGN KEY (asset_id) REFERENCES bank_asset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_33CCCCAF5DA1941 ON bank_asset_valuation (asset_id)');
        $this->addSql('CREATE INDEX IDX_ASSET_VALUATION_DATE ON bank_asset_valuation (asset_id, valued_at)');
        $this->addSql(<<<'SQL'
            CREATE TABLE bank_category_rule (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              user_id INTEGER NOT NULL,
              account_id INTEGER DEFAULT NULL,
              category_id INTEGER NOT NULL,
              public_id VARCHAR(26) NOT NULL,
              match_type VARCHAR(16) NOT NULL,
              pattern VARCHAR(255) NOT NULL,
              minimum_amount NUMERIC(12, 2) DEFAULT NULL,
              maximum_amount NUMERIC(12, 2) DEFAULT NULL,
              priority INTEGER DEFAULT 100 NOT NULL,
              active BOOLEAN DEFAULT 1 NOT NULL,
              archived_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              CONSTRAINT FK_47E68780A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_47E687809B6B5FBA FOREIGN KEY (account_id) REFERENCES bank_account (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_47E6878012469DE2 FOREIGN KEY (category_id) REFERENCES bank_category (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_47E68780B5B48B91 ON bank_category_rule (public_id)');
        $this->addSql('CREATE INDEX IDX_47E68780A76ED395 ON bank_category_rule (user_id)');
        $this->addSql('CREATE INDEX IDX_47E687809B6B5FBA ON bank_category_rule (account_id)');
        $this->addSql('CREATE INDEX IDX_47E6878012469DE2 ON bank_category_rule (category_id)');
        $this->addSql('CREATE INDEX IDX_BANK_RULE_PRIORITY ON bank_category_rule (user_id, priority, archived_at)');
        $this->addSql(<<<'SQL'
            CREATE TABLE bank_reconciliation_batch (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              user_id INTEGER NOT NULL,
              account_id INTEGER NOT NULL,
              public_id VARCHAR(26) NOT NULL,
              filename VARCHAR(255) NOT NULL,
              matched_count INTEGER DEFAULT 0 NOT NULL,
              created_at DATETIME NOT NULL,
              CONSTRAINT FK_DE31C905A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_DE31C9059B6B5FBA FOREIGN KEY (account_id) REFERENCES bank_account (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DE31C905B5B48B91 ON bank_reconciliation_batch (public_id)');
        $this->addSql('CREATE INDEX IDX_DE31C905A76ED395 ON bank_reconciliation_batch (user_id)');
        $this->addSql('CREATE INDEX IDX_DE31C9059B6B5FBA ON bank_reconciliation_batch (account_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE bank_simulation (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              user_id INTEGER NOT NULL,
              public_id VARCHAR(26) NOT NULL,
              name VARCHAR(120) NOT NULL,
              horizon VARCHAR(20) NOT NULL,
              assumptions CLOB NOT NULL --(DC2Type:json)
              ,
              archived_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              CONSTRAINT FK_427A80CDA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_427A80CDB5B48B91 ON bank_simulation (public_id)');
        $this->addSql('CREATE INDEX IDX_427A80CDA76ED395 ON bank_simulation (user_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE bank_transaction_split (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              transaction_id INTEGER NOT NULL,
              user_id INTEGER NOT NULL,
              category_id INTEGER DEFAULT NULL,
              public_id VARCHAR(26) NOT NULL,
              encrypted_label VARCHAR(2048) NOT NULL,
              amount NUMERIC(12, 2) NOT NULL,
              created_at DATETIME NOT NULL,
              CONSTRAINT FK_74A4AB452FC0CB0F FOREIGN KEY (transaction_id) REFERENCES bank_transaction (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_74A4AB45A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_74A4AB4512469DE2 FOREIGN KEY (category_id) REFERENCES bank_category (id) ON DELETE
              SET
                NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_74A4AB45B5B48B91 ON bank_transaction_split (public_id)');
        $this->addSql('CREATE INDEX IDX_74A4AB45A76ED395 ON bank_transaction_split (user_id)');
        $this->addSql('CREATE INDEX IDX_74A4AB4512469DE2 ON bank_transaction_split (category_id)');
        $this->addSql('CREATE INDEX IDX_BANK_SPLIT_TRANSACTION ON bank_transaction_split (transaction_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE calendar_subscription (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              user_id INTEGER NOT NULL,
              nanny_id INTEGER NOT NULL,
              public_id VARCHAR(26) NOT NULL,
              access_token VARCHAR(64) NOT NULL,
              created_at DATETIME NOT NULL,
              regenerated_at DATETIME DEFAULT NULL,
              CONSTRAINT FK_42B80075A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_42B8007516497E83 FOREIGN KEY (nanny_id) REFERENCES nanny (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_42B80075B5B48B91 ON calendar_subscription (public_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_42B80075B6A2DD68 ON calendar_subscription (access_token)');
        $this->addSql('CREATE INDEX IDX_42B80075A76ED395 ON calendar_subscription (user_id)');
        $this->addSql('CREATE INDEX IDX_42B8007516497E83 ON calendar_subscription (nanny_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CALENDAR_USER_NANNY ON calendar_subscription (user_id, nanny_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE care_absence (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              user_id INTEGER NOT NULL,
              nanny_id INTEGER NOT NULL,
              public_id VARCHAR(26) NOT NULL,
              type VARCHAR(32) NOT NULL,
              starts_at DATETIME NOT NULL,
              ends_at DATETIME NOT NULL,
              remunerated BOOLEAN DEFAULT 0 NOT NULL,
              notes CLOB DEFAULT NULL,
              archived_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              CONSTRAINT FK_363B7EFCA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_363B7EFC16497E83 FOREIGN KEY (nanny_id) REFERENCES nanny (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_363B7EFCB5B48B91 ON care_absence (public_id)');
        $this->addSql('CREATE INDEX IDX_363B7EFCA76ED395 ON care_absence (user_id)');
        $this->addSql('CREATE INDEX IDX_363B7EFC16497E83 ON care_absence (nanny_id)');
        $this->addSql('CREATE INDEX IDX_CARE_ABSENCE_OWNER_PERIOD ON care_absence (user_id, nanny_id, starts_at)');
        $this->addSql(<<<'SQL'
            CREATE TABLE nanny_document (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              user_id INTEGER NOT NULL,
              nanny_id INTEGER NOT NULL,
              public_id VARCHAR(26) NOT NULL,
              type VARCHAR(32) NOT NULL,
              encrypted_original_name VARCHAR(2048) NOT NULL,
              mime_type VARCHAR(100) NOT NULL,
              size_bytes INTEGER NOT NULL,
              encrypted_content CLOB NOT NULL,
              content_hash VARCHAR(64) NOT NULL,
              expires_at DATETIME DEFAULT NULL,
              archived_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              CONSTRAINT FK_9CA5D777A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_9CA5D77716497E83 FOREIGN KEY (nanny_id) REFERENCES nanny (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9CA5D777B5B48B91 ON nanny_document (public_id)');
        $this->addSql('CREATE INDEX IDX_9CA5D777A76ED395 ON nanny_document (user_id)');
        $this->addSql('CREATE INDEX IDX_9CA5D77716497E83 ON nanny_document (nanny_id)');
        $this->addSql('CREATE INDEX IDX_NANNY_DOCUMENT_OWNER ON nanny_document (user_id, nanny_id, archived_at)');
        $this->addSql(<<<'SQL'
            CREATE TABLE user_notification (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              user_id INTEGER NOT NULL,
              public_id VARCHAR(26) NOT NULL,
              kind VARCHAR(40) NOT NULL,
              title VARCHAR(180) NOT NULL,
              message CLOB NOT NULL,
              link VARCHAR(500) DEFAULT NULL,
              dedupe_key VARCHAR(190) NOT NULL,
              read_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              CONSTRAINT FK_3F980AC8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3F980AC8B5B48B91 ON user_notification (public_id)');
        $this->addSql('CREATE INDEX IDX_3F980AC8A76ED395 ON user_notification (user_id)');
        $this->addSql('CREATE INDEX IDX_NOTIFICATION_USER_STATE ON user_notification (user_id, read_at, created_at)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_NOTIFICATION_USER_DEDUPE ON user_notification (user_id, dedupe_key)');
        $this->addSql('ALTER TABLE bank_import_batch ADD COLUMN undone_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE bank_transaction ADD COLUMN imported_state_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE bank_transaction ADD COLUMN reconciled_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE care ADD COLUMN status VARCHAR(20) DEFAULT \'planned\' NOT NULL');
        $this->addSql('ALTER TABLE care ADD COLUMN status_locked BOOLEAN DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE care ADD COLUMN remunerated_override BOOLEAN DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE care ADD COLUMN non_realization_reason VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE care ADD COLUMN notes CLOB DEFAULT NULL');
        $this->addSql('ALTER TABLE care ADD COLUMN archived_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE care_template ADD COLUMN archived_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE nanny ADD COLUMN ownership_transferred_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE nanny_rate ADD COLUMN archived_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD COLUMN last_login_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD COLUMN dashboard_preferences CLOB DEFAULT \'{}\' NOT NULL');
        $this->addSql("UPDATE care SET status = 'realized' WHERE date < DATE('now')");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE bank_alert_setting');
        $this->addSql('DROP TABLE bank_analytics_favorite');
        $this->addSql('DROP TABLE bank_asset');
        $this->addSql('DROP TABLE bank_asset_valuation');
        $this->addSql('DROP TABLE bank_category_rule');
        $this->addSql('DROP TABLE bank_reconciliation_batch');
        $this->addSql('DROP TABLE bank_simulation');
        $this->addSql('DROP TABLE bank_transaction_split');
        $this->addSql('DROP TABLE calendar_subscription');
        $this->addSql('DROP TABLE care_absence');
        $this->addSql('DROP TABLE nanny_document');
        $this->addSql('DROP TABLE user_notification');
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__bank_import_batch AS
            SELECT
              id,
              user_id,
              account_id,
              profile_id,
              public_id,
              original_filename,
              imported_count,
              skipped_count,
              error_count,
              errors,
              created_at
            FROM
              bank_import_batch
        SQL);
        $this->addSql('DROP TABLE bank_import_batch');
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
              CONSTRAINT FK_D5C61A01A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_D5C61A019B6B5FBA FOREIGN KEY (account_id) REFERENCES bank_account (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_D5C61A01CCFA12B8 FOREIGN KEY (profile_id) REFERENCES bank_import_profile (id) ON DELETE
              SET
                NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO bank_import_batch (
              id, user_id, account_id, profile_id,
              public_id, original_filename, imported_count,
              skipped_count, error_count, errors,
              created_at
            )
            SELECT
              id,
              user_id,
              account_id,
              profile_id,
              public_id,
              original_filename,
              imported_count,
              skipped_count,
              error_count,
              errors,
              created_at
            FROM
              __temp__bank_import_batch
        SQL);
        $this->addSql('DROP TABLE __temp__bank_import_batch');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D5C61A01B5B48B91 ON bank_import_batch (public_id)');
        $this->addSql('CREATE INDEX IDX_D5C61A01A76ED395 ON bank_import_batch (user_id)');
        $this->addSql('CREATE INDEX IDX_D5C61A019B6B5FBA ON bank_import_batch (account_id)');
        $this->addSql('CREATE INDEX IDX_D5C61A01CCFA12B8 ON bank_import_batch (profile_id)');
        $this->addSql('CREATE INDEX IDX_BANK_IMPORT_BATCH_USER_DATE ON bank_import_batch (user_id, created_at)');
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__bank_transaction AS
            SELECT
              id,
              user_id,
              account_id,
              category_id,
              import_batch_id,
              recurring_rule_id,
              monthly_payment_id,
              loan_account_id,
              mirror_transaction_id,
              public_id,
              transaction_date,
              encrypted_label,
              amount,
              type,
              status,
              source,
              verified_at,
              import_hash,
              transfer_group,
              archived_at,
              created_at,
              updated_at
            FROM
              bank_transaction
        SQL);
        $this->addSql('DROP TABLE bank_transaction');
        $this->addSql(<<<'SQL'
            CREATE TABLE bank_transaction (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              user_id INTEGER NOT NULL,
              account_id INTEGER NOT NULL,
              category_id INTEGER DEFAULT NULL,
              import_batch_id INTEGER DEFAULT NULL,
              recurring_rule_id INTEGER DEFAULT NULL,
              monthly_payment_id INTEGER DEFAULT NULL,
              loan_account_id INTEGER DEFAULT NULL,
              mirror_transaction_id INTEGER DEFAULT NULL,
              public_id VARCHAR(26) NOT NULL,
              transaction_date DATE NOT NULL,
              encrypted_label VARCHAR(2048) NOT NULL,
              amount NUMERIC(12, 2) NOT NULL,
              type VARCHAR(24) NOT NULL,
              status VARCHAR(24) NOT NULL,
              source VARCHAR(24) NOT NULL,
              verified_at DATETIME DEFAULT NULL,
              import_hash VARCHAR(64) DEFAULT NULL,
              transfer_group VARCHAR(26) DEFAULT NULL,
              archived_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              CONSTRAINT FK_50BCB3AEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_50BCB3AE9B6B5FBA FOREIGN KEY (account_id) REFERENCES bank_account (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_50BCB3AE12469DE2 FOREIGN KEY (category_id) REFERENCES bank_category (id) ON DELETE
              SET
                NULL NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_50BCB3AE5A310080 FOREIGN KEY (import_batch_id) REFERENCES bank_import_batch (id) ON DELETE
              SET
                NULL NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_50BCB3AEACB896D8 FOREIGN KEY (recurring_rule_id) REFERENCES bank_recurring_rule (id) ON DELETE
              SET
                NULL NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_50BCB3AECDDEC33E FOREIGN KEY (monthly_payment_id) REFERENCES monthly_payment (id) ON DELETE
              SET
                NULL NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_50BCB3AE8BF076DC FOREIGN KEY (loan_account_id) REFERENCES bank_account (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_50BCB3AE544901D2 FOREIGN KEY (mirror_transaction_id) REFERENCES bank_transaction (id) ON DELETE
              SET
                NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO bank_transaction (
              id, user_id, account_id, category_id,
              import_batch_id, recurring_rule_id,
              monthly_payment_id, loan_account_id,
              mirror_transaction_id, public_id,
              transaction_date, encrypted_label,
              amount, type, status, source, verified_at,
              import_hash, transfer_group, archived_at,
              created_at, updated_at
            )
            SELECT
              id,
              user_id,
              account_id,
              category_id,
              import_batch_id,
              recurring_rule_id,
              monthly_payment_id,
              loan_account_id,
              mirror_transaction_id,
              public_id,
              transaction_date,
              encrypted_label,
              amount,
              type,
              status,
              source,
              verified_at,
              import_hash,
              transfer_group,
              archived_at,
              created_at,
              updated_at
            FROM
              __temp__bank_transaction
        SQL);
        $this->addSql('DROP TABLE __temp__bank_transaction');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_50BCB3AEB5B48B91 ON bank_transaction (public_id)');
        $this->addSql('CREATE INDEX IDX_50BCB3AEA76ED395 ON bank_transaction (user_id)');
        $this->addSql('CREATE INDEX IDX_50BCB3AE9B6B5FBA ON bank_transaction (account_id)');
        $this->addSql('CREATE INDEX IDX_50BCB3AE12469DE2 ON bank_transaction (category_id)');
        $this->addSql('CREATE INDEX IDX_50BCB3AE5A310080 ON bank_transaction (import_batch_id)');
        $this->addSql('CREATE INDEX IDX_50BCB3AEACB896D8 ON bank_transaction (recurring_rule_id)');
        $this->addSql('CREATE INDEX IDX_50BCB3AECDDEC33E ON bank_transaction (monthly_payment_id)');
        $this->addSql('CREATE INDEX IDX_50BCB3AE8BF076DC ON bank_transaction (loan_account_id)');
        $this->addSql('CREATE INDEX IDX_50BCB3AE544901D2 ON bank_transaction (mirror_transaction_id)');
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_BANK_TRANSACTION_USER_ACCOUNT_DATE ON bank_transaction (
              user_id, account_id, transaction_date
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_BANK_TRANSACTION_USER_CATEGORY_DATE ON bank_transaction (
              user_id, category_id, transaction_date
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_BANK_TRANSACTION_USER_STATUS_DATE ON bank_transaction (
              user_id, status, transaction_date
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_BANK_TRANSACTION_USER_VERIFIED ON bank_transaction (user_id, verified_at)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_BANK_TRANSACTION_IMPORT_HASH ON bank_transaction (user_id, account_id, import_hash)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__care AS
            SELECT
              id,
              user_id,
              nanny_id,
              public_id,
              nanny_display_name,
              nanny_snapshot_key,
              date,
              start_time,
              end_time,
              meals_count,
              hours_count,
              month,
              segment_group,
              segment_index,
              segment_total,
              created_at
            FROM
              care
        SQL);
        $this->addSql('DROP TABLE care');
        $this->addSql(<<<'SQL'
            CREATE TABLE care (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              user_id INTEGER NOT NULL,
              nanny_id INTEGER DEFAULT NULL,
              public_id VARCHAR(26) NOT NULL,
              nanny_display_name VARCHAR(255) NOT NULL,
              nanny_snapshot_key VARCHAR(26) DEFAULT NULL,
              date DATE NOT NULL,
              start_time TIME NOT NULL,
              end_time TIME NOT NULL,
              meals_count SMALLINT DEFAULT 0 NOT NULL,
              hours_count NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
              month VARCHAR(7) NOT NULL,
              segment_group VARCHAR(26) DEFAULT NULL,
              segment_index INTEGER DEFAULT 1 NOT NULL,
              segment_total INTEGER DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              CONSTRAINT FK_6113A845A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_6113A84516497E83 FOREIGN KEY (nanny_id) REFERENCES nanny (id) ON DELETE
              SET
                NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO care (
              id, user_id, nanny_id, public_id, nanny_display_name,
              nanny_snapshot_key, date, start_time,
              end_time, meals_count, hours_count,
              month, segment_group, segment_index,
              segment_total, created_at
            )
            SELECT
              id,
              user_id,
              nanny_id,
              public_id,
              nanny_display_name,
              nanny_snapshot_key,
              date,
              start_time,
              end_time,
              meals_count,
              hours_count,
              month,
              segment_group,
              segment_index,
              segment_total,
              created_at
            FROM
              __temp__care
        SQL);
        $this->addSql('DROP TABLE __temp__care');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6113A845B5B48B91 ON care (public_id)');
        $this->addSql('CREATE INDEX IDX_6113A845A76ED395 ON care (user_id)');
        $this->addSql('CREATE INDEX IDX_6113A84516497E83 ON care (nanny_id)');
        $this->addSql('CREATE INDEX IDX_CARE_USER_NANNY_DATE ON care (user_id, nanny_id, date)');
        $this->addSql('CREATE INDEX IDX_CARE_USER_NANNY_SNAPSHOT_MONTH ON care (user_id, nanny_snapshot_key, month)');
        $this->addSql('CREATE INDEX IDX_CARE_USER_MONTH ON care (user_id, month)');
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__care_template AS
            SELECT
              id,
              user_id,
              nanny_id,
              public_id,
              name,
              week_schedule,
              created_at
            FROM
              care_template
        SQL);
        $this->addSql('DROP TABLE care_template');
        $this->addSql(<<<'SQL'
            CREATE TABLE care_template (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              user_id INTEGER NOT NULL,
              nanny_id INTEGER DEFAULT NULL,
              public_id VARCHAR(26) NOT NULL,
              name VARCHAR(255) NOT NULL,
              week_schedule CLOB NOT NULL --(DC2Type:json)
              ,
              created_at DATETIME NOT NULL,
              CONSTRAINT FK_C193BA3EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_C193BA3E16497E83 FOREIGN KEY (nanny_id) REFERENCES nanny (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO care_template (
              id, user_id, nanny_id, public_id, name,
              week_schedule, created_at
            )
            SELECT
              id,
              user_id,
              nanny_id,
              public_id,
              name,
              week_schedule,
              created_at
            FROM
              __temp__care_template
        SQL);
        $this->addSql('DROP TABLE __temp__care_template');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C193BA3EB5B48B91 ON care_template (public_id)');
        $this->addSql('CREATE INDEX IDX_C193BA3EA76ED395 ON care_template (user_id)');
        $this->addSql('CREATE INDEX IDX_C193BA3E16497E83 ON care_template (nanny_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_TEMPLATE_USER_NANNY_NAME ON care_template (user_id, nanny_id, name)');
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__nanny AS
            SELECT
              id,
              created_by_id,
              public_id,
              firstname,
              lastname,
              address_line1,
              address_line2,
              postal_code,
              city,
              country,
              invite_code,
              created_at
            FROM
              nanny
        SQL);
        $this->addSql('DROP TABLE nanny');
        $this->addSql(<<<'SQL'
            CREATE TABLE nanny (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              created_by_id INTEGER DEFAULT NULL,
              public_id VARCHAR(26) NOT NULL,
              firstname VARCHAR(120) NOT NULL,
              lastname VARCHAR(120) NOT NULL,
              address_line1 VARCHAR(255) DEFAULT NULL,
              address_line2 VARCHAR(255) DEFAULT NULL,
              postal_code VARCHAR(20) DEFAULT NULL,
              city VARCHAR(120) DEFAULT NULL,
              country VARCHAR(80) DEFAULT NULL,
              invite_code VARCHAR(40) NOT NULL,
              created_at DATETIME NOT NULL,
              CONSTRAINT FK_F354C7CCB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO nanny (
              id, created_by_id, public_id, firstname,
              lastname, address_line1, address_line2,
              postal_code, city, country, invite_code,
              created_at
            )
            SELECT
              id,
              created_by_id,
              public_id,
              firstname,
              lastname,
              address_line1,
              address_line2,
              postal_code,
              city,
              country,
              invite_code,
              created_at
            FROM
              __temp__nanny
        SQL);
        $this->addSql('DROP TABLE __temp__nanny');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F354C7CCB5B48B91 ON nanny (public_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F354C7CC6F21F112 ON nanny (invite_code)');
        $this->addSql('CREATE INDEX IDX_F354C7CCB03A8386 ON nanny (created_by_id)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_NANNY_IDENTITY_LOCATION ON nanny (
              firstname, lastname, postal_code,
              city
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__nanny_rate AS
            SELECT
              id,
              user_id,
              nanny_id,
              public_id,
              nanny_snapshot_key,
              hourly_rate,
              meal_rate,
              effective_from,
              created_at
            FROM
              nanny_rate
        SQL);
        $this->addSql('DROP TABLE nanny_rate');
        $this->addSql(<<<'SQL'
            CREATE TABLE nanny_rate (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              user_id INTEGER NOT NULL,
              nanny_id INTEGER DEFAULT NULL,
              public_id VARCHAR(26) NOT NULL,
              nanny_snapshot_key VARCHAR(26) DEFAULT NULL,
              hourly_rate NUMERIC(10, 2) NOT NULL,
              meal_rate NUMERIC(10, 2) NOT NULL,
              effective_from DATE NOT NULL,
              created_at DATETIME NOT NULL,
              CONSTRAINT FK_1192A855A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_1192A85516497E83 FOREIGN KEY (nanny_id) REFERENCES nanny (id) ON DELETE
              SET
                NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO nanny_rate (
              id, user_id, nanny_id, public_id, nanny_snapshot_key,
              hourly_rate, meal_rate, effective_from,
              created_at
            )
            SELECT
              id,
              user_id,
              nanny_id,
              public_id,
              nanny_snapshot_key,
              hourly_rate,
              meal_rate,
              effective_from,
              created_at
            FROM
              __temp__nanny_rate
        SQL);
        $this->addSql('DROP TABLE __temp__nanny_rate');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1192A855B5B48B91 ON nanny_rate (public_id)');
        $this->addSql('CREATE INDEX IDX_1192A855A76ED395 ON nanny_rate (user_id)');
        $this->addSql('CREATE INDEX IDX_1192A85516497E83 ON nanny_rate (nanny_id)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_NANNY_RATE_EFFECTIVE_DATE ON nanny_rate (
              user_id, nanny_id, effective_from
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_NANNY_RATE_SNAPSHOT_EFFECTIVE_DATE ON nanny_rate (
              user_id, nanny_snapshot_key, effective_from
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__user AS
            SELECT
              id,
              public_id,
              username,
              email,
              email_verified,
              email_verification_token_hash,
              email_verification_requested_at,
              roles,
              password,
              created_at
            FROM
              user
        SQL);
        $this->addSql('DROP TABLE user');
        $this->addSql(<<<'SQL'
            CREATE TABLE user (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              public_id VARCHAR(26) NOT NULL,
              username VARCHAR(180) NOT NULL,
              email VARCHAR(255) DEFAULT NULL,
              email_verified BOOLEAN DEFAULT 0 NOT NULL,
              email_verification_token_hash VARCHAR(128) DEFAULT NULL,
              email_verification_requested_at DATETIME DEFAULT NULL,
              roles CLOB NOT NULL --(DC2Type:json)
              ,
              password VARCHAR(255) NOT NULL,
              created_at DATETIME NOT NULL
            )
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO user (
              id, public_id, username, email, email_verified,
              email_verification_token_hash,
              email_verification_requested_at,
              roles, password, created_at
            )
            SELECT
              id,
              public_id,
              username,
              email,
              email_verified,
              email_verification_token_hash,
              email_verification_requested_at,
              roles,
              password,
              created_at
            FROM
              __temp__user
        SQL);
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649B5B48B91 ON user (public_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON user (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_EMAIL ON user (email)');
    }
}
