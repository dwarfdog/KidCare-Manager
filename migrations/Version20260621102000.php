<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260621102000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize activity log SQLite metadata to match Doctrine schema inspection.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'This migration is generated for SQLite.');

        $this->addSql('CREATE TEMPORARY TABLE __temp__activity_log AS SELECT id, user_id, public_id, actor_identifier, "action", message, context, created_at FROM activity_log');
        $this->addSql('DROP TABLE activity_log');
        $this->addSql(<<<'SQL'
            CREATE TABLE activity_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                user_id INTEGER DEFAULT NULL,
                public_id VARCHAR(26) NOT NULL,
                actor_identifier VARCHAR(180) DEFAULT NULL,
                "action" VARCHAR(80) NOT NULL,
                message VARCHAR(255) NOT NULL,
                context CLOB NOT NULL --(DC2Type:json)
                ,
                created_at DATETIME NOT NULL,
                CONSTRAINT FK_FD5DD3A9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('INSERT INTO activity_log (id, user_id, public_id, actor_identifier, "action", message, context, created_at) SELECT id, user_id, public_id, actor_identifier, "action", message, context, created_at FROM __temp__activity_log');
        $this->addSql('DROP TABLE __temp__activity_log');
        $this->addSql('CREATE INDEX IDX_ACTIVITY_ACTION_DATE ON activity_log ("action", created_at)');
        $this->addSql('CREATE INDEX IDX_ACTIVITY_ACTOR_DATE ON activity_log (actor_identifier, created_at)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FD06F647B5B48B91 ON activity_log (public_id)');
        $this->addSql('CREATE INDEX IDX_FD06F647A76ED395 ON activity_log (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'This migration is generated for SQLite.');

        $this->addSql('CREATE TEMPORARY TABLE __temp__activity_log AS SELECT id, user_id, public_id, actor_identifier, "action", message, context, created_at FROM activity_log');
        $this->addSql('DROP TABLE activity_log');
        $this->addSql(<<<'SQL'
            CREATE TABLE activity_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                user_id INTEGER DEFAULT NULL,
                public_id VARCHAR(26) NOT NULL,
                actor_identifier VARCHAR(180) DEFAULT NULL,
                action VARCHAR(80) NOT NULL,
                message VARCHAR(255) NOT NULL,
                context CLOB NOT NULL --(DC2Type:json)
                ,
                created_at DATETIME NOT NULL,
                CONSTRAINT FK_FD5DD3A9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('INSERT INTO activity_log (id, user_id, public_id, actor_identifier, action, message, context, created_at) SELECT id, user_id, public_id, actor_identifier, "action", message, context, created_at FROM __temp__activity_log');
        $this->addSql('DROP TABLE __temp__activity_log');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FD5DD3A9B5B48B91 ON activity_log (public_id)');
        $this->addSql('CREATE INDEX IDX_FD5DD3A9A76ED395 ON activity_log (user_id)');
        $this->addSql('CREATE INDEX IDX_ACTIVITY_ACTOR_DATE ON activity_log (actor_identifier, created_at)');
        $this->addSql('CREATE INDEX IDX_ACTIVITY_ACTION_DATE ON activity_log (action, created_at)');
    }
}
