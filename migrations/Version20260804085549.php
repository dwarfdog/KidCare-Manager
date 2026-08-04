<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260804085549 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute course_receipt.encrypted_store_name_guess (nom de magasin detecte par l\'OCR, conserve pour apprendre l\'alias magasin lors de la confirmation du ticket).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course_receipt ADD COLUMN encrypted_store_name_guess VARCHAR(2048) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__course_receipt AS SELECT id, user_id, store_id, account_id, bank_transaction_id, public_id, purchase_date, total_amount, source_format, status, encrypted_ocr_raw_text, processing_errors, confirmed_at, archived_at, created_at, updated_at FROM course_receipt');
        $this->addSql('DROP TABLE course_receipt');
        $this->addSql('CREATE TABLE course_receipt (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, store_id INTEGER DEFAULT NULL, account_id INTEGER DEFAULT NULL, bank_transaction_id INTEGER DEFAULT NULL, public_id VARCHAR(26) NOT NULL, purchase_date DATE DEFAULT NULL, total_amount NUMERIC(12, 2) NOT NULL, source_format VARCHAR(24) NOT NULL, status VARCHAR(24) NOT NULL, encrypted_ocr_raw_text CLOB DEFAULT NULL, processing_errors CLOB NOT NULL --(DC2Type:json)
        , confirmed_at DATETIME DEFAULT NULL, archived_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_5B5AB3A9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5B5AB3A9B092A811 FOREIGN KEY (store_id) REFERENCES course_store (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5B5AB3A99B6B5FBA FOREIGN KEY (account_id) REFERENCES bank_account (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5B5AB3A9B898B7D6 FOREIGN KEY (bank_transaction_id) REFERENCES bank_transaction (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO course_receipt (id, user_id, store_id, account_id, bank_transaction_id, public_id, purchase_date, total_amount, source_format, status, encrypted_ocr_raw_text, processing_errors, confirmed_at, archived_at, created_at, updated_at) SELECT id, user_id, store_id, account_id, bank_transaction_id, public_id, purchase_date, total_amount, source_format, status, encrypted_ocr_raw_text, processing_errors, confirmed_at, archived_at, created_at, updated_at FROM __temp__course_receipt');
        $this->addSql('DROP TABLE __temp__course_receipt');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5B5AB3A9B5B48B91 ON course_receipt (public_id)');
        $this->addSql('CREATE INDEX IDX_5B5AB3A9A76ED395 ON course_receipt (user_id)');
        $this->addSql('CREATE INDEX IDX_5B5AB3A9B092A811 ON course_receipt (store_id)');
        $this->addSql('CREATE INDEX IDX_5B5AB3A99B6B5FBA ON course_receipt (account_id)');
        $this->addSql('CREATE INDEX IDX_5B5AB3A9B898B7D6 ON course_receipt (bank_transaction_id)');
        $this->addSql('CREATE INDEX IDX_COURSE_RECEIPT_USER_STATUS_DATE ON course_receipt (user_id, status, purchase_date)');
        $this->addSql('CREATE INDEX IDX_COURSE_RECEIPT_USER_STORE ON course_receipt (user_id, store_id)');
        $this->addSql('CREATE INDEX IDX_COURSE_RECEIPT_USER_ARCHIVE ON course_receipt (user_id, archived_at)');
    }
}
