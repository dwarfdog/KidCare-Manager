<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802211456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserve the Doctrine JSON type metadata for dashboard preferences on SQLite.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
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
              created_at,
              last_login_at,
              dashboard_preferences
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
              created_at DATETIME NOT NULL,
              last_login_at DATETIME DEFAULT NULL,
              dashboard_preferences CLOB DEFAULT '{}' NOT NULL --(DC2Type:json)
            )
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO user (
              id, public_id, username, email, email_verified,
              email_verification_token_hash,
              email_verification_requested_at,
              roles, password, created_at, last_login_at,
              dashboard_preferences
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
              created_at,
              last_login_at,
              dashboard_preferences
            FROM
              __temp__user
        SQL);
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_EMAIL ON user (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON user (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649B5B48B91 ON user (public_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
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
              created_at,
              last_login_at,
              dashboard_preferences
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
              created_at DATETIME NOT NULL,
              last_login_at DATETIME DEFAULT NULL,
              dashboard_preferences CLOB DEFAULT '{}' NOT NULL
            )
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO user (
              id, public_id, username, email, email_verified,
              email_verification_token_hash,
              email_verification_requested_at,
              roles, password, created_at, last_login_at,
              dashboard_preferences
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
              created_at,
              last_login_at,
              dashboard_preferences
            FROM
              __temp__user
        SQL);
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649B5B48B91 ON user (public_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON user (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_EMAIL ON user (email)');
    }
}
