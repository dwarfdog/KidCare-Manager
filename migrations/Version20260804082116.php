<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260804082116 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le module Courses : magasins, categories, produits, tickets/lignes, pieces jointes, profils d\'import, listes de courses et journal d\'audit.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE course_attachment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, receipt_id INTEGER NOT NULL, public_id VARCHAR(26) NOT NULL, encrypted_original_name VARCHAR(1024) NOT NULL, mime_type VARCHAR(120) NOT NULL, size_bytes INTEGER NOT NULL, content BLOB NOT NULL, content_hash VARCHAR(64) NOT NULL, archived_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_A6BD9738A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A6BD97382B5CA896 FOREIGN KEY (receipt_id) REFERENCES course_receipt (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A6BD9738B5B48B91 ON course_attachment (public_id)');
        $this->addSql('CREATE INDEX IDX_A6BD9738A76ED395 ON course_attachment (user_id)');
        $this->addSql('CREATE INDEX IDX_A6BD97382B5CA896 ON course_attachment (receipt_id)');
        $this->addSql('CREATE INDEX IDX_COURSE_ATTACHMENT_USER_RECEIPT ON course_attachment (user_id, receipt_id)');
        $this->addSql('CREATE TABLE course_audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER DEFAULT NULL, public_id VARCHAR(26) NOT NULL, actor_identifier VARCHAR(180) DEFAULT NULL, "action" VARCHAR(80) NOT NULL, entity_type VARCHAR(80) NOT NULL, entity_public_id VARCHAR(40) DEFAULT NULL, context CLOB NOT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL, CONSTRAINT FK_74408059A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_74408059B5B48B91 ON course_audit_log (public_id)');
        $this->addSql('CREATE INDEX IDX_74408059A76ED395 ON course_audit_log (user_id)');
        $this->addSql('CREATE INDEX IDX_COURSE_AUDIT_USER_DATE ON course_audit_log (user_id, created_at)');
        $this->addSql('CREATE INDEX IDX_COURSE_AUDIT_ENTITY ON course_audit_log (entity_type, entity_public_id)');
        $this->addSql('CREATE TABLE course_category (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, parent_category_id INTEGER NOT NULL, public_id VARCHAR(26) NOT NULL, name VARCHAR(120) NOT NULL, color VARCHAR(24) NOT NULL, icon VARCHAR(60) NOT NULL, is_default BOOLEAN DEFAULT 0 NOT NULL, archived_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_AFF87497A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AFF87497796A8F92 FOREIGN KEY (parent_category_id) REFERENCES bank_category (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AFF87497B5B48B91 ON course_category (public_id)');
        $this->addSql('CREATE INDEX IDX_AFF87497A76ED395 ON course_category (user_id)');
        $this->addSql('CREATE INDEX IDX_AFF87497796A8F92 ON course_category (parent_category_id)');
        $this->addSql('CREATE INDEX IDX_COURSE_CATEGORY_USER_ARCHIVE ON course_category (user_id, archived_at)');
        $this->addSql('CREATE TABLE course_import_profile (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, public_id VARCHAR(26) NOT NULL, name VARCHAR(120) NOT NULL, delimiter VARCHAR(8) NOT NULL, date_format VARCHAR(30) NOT NULL, mapping CLOB NOT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL, CONSTRAINT FK_6D8BE4D7A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6D8BE4D7B5B48B91 ON course_import_profile (public_id)');
        $this->addSql('CREATE INDEX IDX_COURSE_IMPORT_PROFILE_USER ON course_import_profile (user_id)');
        $this->addSql('CREATE TABLE course_product (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, category_id INTEGER DEFAULT NULL, public_id VARCHAR(26) NOT NULL, encrypted_name VARCHAR(2048) NOT NULL, default_unit VARCHAR(12) NOT NULL, package_quantity NUMERIC(10, 3) DEFAULT NULL, archived_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_DB890141A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_DB89014112469DE2 FOREIGN KEY (category_id) REFERENCES course_category (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DB890141B5B48B91 ON course_product (public_id)');
        $this->addSql('CREATE INDEX IDX_DB890141A76ED395 ON course_product (user_id)');
        $this->addSql('CREATE INDEX IDX_DB89014112469DE2 ON course_product (category_id)');
        $this->addSql('CREATE INDEX IDX_COURSE_PRODUCT_USER_ARCHIVE ON course_product (user_id, archived_at)');
        $this->addSql('CREATE INDEX IDX_COURSE_PRODUCT_USER_CATEGORY ON course_product (user_id, category_id)');
        $this->addSql('CREATE TABLE course_product_alias (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, product_id INTEGER NOT NULL, public_id VARCHAR(26) NOT NULL, normalized_label VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_83BD5411A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_83BD54114584665A FOREIGN KEY (product_id) REFERENCES course_product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_83BD5411B5B48B91 ON course_product_alias (public_id)');
        $this->addSql('CREATE INDEX IDX_83BD5411A76ED395 ON course_product_alias (user_id)');
        $this->addSql('CREATE INDEX IDX_83BD54114584665A ON course_product_alias (product_id)');
        $this->addSql('CREATE INDEX IDX_COURSE_PRODUCT_ALIAS_USER_LABEL ON course_product_alias (user_id, normalized_label)');
        $this->addSql('CREATE TABLE course_receipt (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, store_id INTEGER DEFAULT NULL, account_id INTEGER DEFAULT NULL, bank_transaction_id INTEGER DEFAULT NULL, public_id VARCHAR(26) NOT NULL, purchase_date DATE DEFAULT NULL, total_amount NUMERIC(12, 2) NOT NULL, source_format VARCHAR(24) NOT NULL, status VARCHAR(24) NOT NULL, encrypted_ocr_raw_text CLOB DEFAULT NULL, processing_errors CLOB NOT NULL --(DC2Type:json)
        , confirmed_at DATETIME DEFAULT NULL, archived_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_5B5AB3A9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5B5AB3A9B092A811 FOREIGN KEY (store_id) REFERENCES course_store (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5B5AB3A99B6B5FBA FOREIGN KEY (account_id) REFERENCES bank_account (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5B5AB3A9B898B7D6 FOREIGN KEY (bank_transaction_id) REFERENCES bank_transaction (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5B5AB3A9B5B48B91 ON course_receipt (public_id)');
        $this->addSql('CREATE INDEX IDX_5B5AB3A9A76ED395 ON course_receipt (user_id)');
        $this->addSql('CREATE INDEX IDX_5B5AB3A9B092A811 ON course_receipt (store_id)');
        $this->addSql('CREATE INDEX IDX_5B5AB3A99B6B5FBA ON course_receipt (account_id)');
        $this->addSql('CREATE INDEX IDX_5B5AB3A9B898B7D6 ON course_receipt (bank_transaction_id)');
        $this->addSql('CREATE INDEX IDX_COURSE_RECEIPT_USER_STATUS_DATE ON course_receipt (user_id, status, purchase_date)');
        $this->addSql('CREATE INDEX IDX_COURSE_RECEIPT_USER_STORE ON course_receipt (user_id, store_id)');
        $this->addSql('CREATE INDEX IDX_COURSE_RECEIPT_USER_ARCHIVE ON course_receipt (user_id, archived_at)');
        $this->addSql('CREATE TABLE course_receipt_line (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, receipt_id INTEGER NOT NULL, user_id INTEGER NOT NULL, product_id INTEGER DEFAULT NULL, category_id INTEGER DEFAULT NULL, public_id VARCHAR(26) NOT NULL, encrypted_raw_label VARCHAR(2048) NOT NULL, quantity NUMERIC(10, 3) NOT NULL, unit VARCHAR(12) NOT NULL, unit_price NUMERIC(10, 4) DEFAULT NULL, line_total NUMERIC(12, 2) NOT NULL, confidence VARCHAR(12) NOT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_C6A5F7872B5CA896 FOREIGN KEY (receipt_id) REFERENCES course_receipt (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C6A5F787A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C6A5F7874584665A FOREIGN KEY (product_id) REFERENCES course_product (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C6A5F78712469DE2 FOREIGN KEY (category_id) REFERENCES course_category (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C6A5F787B5B48B91 ON course_receipt_line (public_id)');
        $this->addSql('CREATE INDEX IDX_C6A5F787A76ED395 ON course_receipt_line (user_id)');
        $this->addSql('CREATE INDEX IDX_C6A5F7874584665A ON course_receipt_line (product_id)');
        $this->addSql('CREATE INDEX IDX_C6A5F78712469DE2 ON course_receipt_line (category_id)');
        $this->addSql('CREATE INDEX IDX_COURSE_LINE_USER_PRODUCT ON course_receipt_line (user_id, product_id)');
        $this->addSql('CREATE INDEX IDX_COURSE_LINE_RECEIPT ON course_receipt_line (receipt_id)');
        $this->addSql('CREATE TABLE course_shopping_list (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, public_id VARCHAR(26) NOT NULL, name VARCHAR(160) NOT NULL, completed_at DATETIME DEFAULT NULL, archived_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_FE4CE080A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FE4CE080B5B48B91 ON course_shopping_list (public_id)');
        $this->addSql('CREATE INDEX IDX_FE4CE080A76ED395 ON course_shopping_list (user_id)');
        $this->addSql('CREATE INDEX IDX_COURSE_SHOPPING_LIST_USER_ARCHIVE ON course_shopping_list (user_id, archived_at)');
        $this->addSql('CREATE TABLE course_shopping_list_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, shopping_list_id INTEGER NOT NULL, product_id INTEGER NOT NULL, public_id VARCHAR(26) NOT NULL, desired_quantity NUMERIC(10, 3) NOT NULL, unit VARCHAR(12) NOT NULL, checked BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_D6181C3823245BF9 FOREIGN KEY (shopping_list_id) REFERENCES course_shopping_list (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_D6181C384584665A FOREIGN KEY (product_id) REFERENCES course_product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D6181C38B5B48B91 ON course_shopping_list_item (public_id)');
        $this->addSql('CREATE INDEX IDX_D6181C384584665A ON course_shopping_list_item (product_id)');
        $this->addSql('CREATE INDEX IDX_COURSE_LIST_ITEM_LIST ON course_shopping_list_item (shopping_list_id)');
        $this->addSql('CREATE TABLE course_store (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, public_id VARCHAR(26) NOT NULL, encrypted_name VARCHAR(2048) NOT NULL, chain VARCHAR(120) DEFAULT NULL, encrypted_address VARCHAR(2048) DEFAULT NULL, archived_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_16FC5F9FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_16FC5F9FB5B48B91 ON course_store (public_id)');
        $this->addSql('CREATE INDEX IDX_16FC5F9FA76ED395 ON course_store (user_id)');
        $this->addSql('CREATE INDEX IDX_COURSE_STORE_USER_ARCHIVE ON course_store (user_id, archived_at)');
        $this->addSql('CREATE INDEX IDX_COURSE_STORE_USER_CHAIN ON course_store (user_id, chain)');
        $this->addSql('CREATE TABLE course_store_alias (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, store_id INTEGER NOT NULL, public_id VARCHAR(26) NOT NULL, normalized_label VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_E93C0D59A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_E93C0D59B092A811 FOREIGN KEY (store_id) REFERENCES course_store (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E93C0D59B5B48B91 ON course_store_alias (public_id)');
        $this->addSql('CREATE INDEX IDX_E93C0D59A76ED395 ON course_store_alias (user_id)');
        $this->addSql('CREATE INDEX IDX_E93C0D59B092A811 ON course_store_alias (store_id)');
        $this->addSql('CREATE INDEX IDX_COURSE_STORE_ALIAS_USER_LABEL ON course_store_alias (user_id, normalized_label)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE course_attachment');
        $this->addSql('DROP TABLE course_audit_log');
        $this->addSql('DROP TABLE course_category');
        $this->addSql('DROP TABLE course_import_profile');
        $this->addSql('DROP TABLE course_product');
        $this->addSql('DROP TABLE course_product_alias');
        $this->addSql('DROP TABLE course_receipt');
        $this->addSql('DROP TABLE course_receipt_line');
        $this->addSql('DROP TABLE course_shopping_list');
        $this->addSql('DROP TABLE course_shopping_list_item');
        $this->addSql('DROP TABLE course_store');
        $this->addSql('DROP TABLE course_store_alias');
    }
}
