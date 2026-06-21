<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620211116 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rebuild the SQLite schema for shared nannies, private rate history, public IDs, calendar segments, and email verification.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'This migration is generated for SQLite.');

        $this->addSql('PRAGMA foreign_keys = OFF');
        foreach ([
            'care',
            'care_template',
            'monthly_payment',
            'nanny_assignment',
            'nanny_rate',
            'nanny_user',
            'nanny',
            'user',
            'messenger_messages',
        ] as $table) {
            $this->addSql(sprintf('DROP TABLE IF EXISTS %s', $table));
        }
        $this->addSql('PRAGMA foreign_keys = ON');

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
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649B5B48B91 ON user (public_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON user (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_EMAIL ON user (email)');

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
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F354C7CCB5B48B91 ON nanny (public_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F354C7CC6F21F112 ON nanny (invite_code)');
        $this->addSql('CREATE INDEX IDX_F354C7CCB03A8386 ON nanny (created_by_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_NANNY_IDENTITY_LOCATION ON nanny (firstname, lastname, postal_code, city)');

        $this->addSql(<<<'SQL'
            CREATE TABLE nanny_assignment (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                user_id INTEGER NOT NULL,
                nanny_id INTEGER NOT NULL,
                public_id VARCHAR(26) NOT NULL,
                attached_at DATETIME NOT NULL,
                detached_at DATETIME DEFAULT NULL,
                CONSTRAINT FK_3C041C7EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_3C041C7E16497E83 FOREIGN KEY (nanny_id) REFERENCES nanny (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3C041C7EB5B48B91 ON nanny_assignment (public_id)');
        $this->addSql('CREATE INDEX IDX_3C041C7EA76ED395 ON nanny_assignment (user_id)');
        $this->addSql('CREATE INDEX IDX_3C041C7E16497E83 ON nanny_assignment (nanny_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_NANNY_ASSIGNMENT_USER_NANNY ON nanny_assignment (user_id, nanny_id)');

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
                CONSTRAINT FK_1192A85516497E83 FOREIGN KEY (nanny_id) REFERENCES nanny (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1192A855B5B48B91 ON nanny_rate (public_id)');
        $this->addSql('CREATE INDEX IDX_1192A855A76ED395 ON nanny_rate (user_id)');
        $this->addSql('CREATE INDEX IDX_1192A85516497E83 ON nanny_rate (nanny_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_NANNY_RATE_EFFECTIVE_DATE ON nanny_rate (user_id, nanny_id, effective_from)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_NANNY_RATE_SNAPSHOT_EFFECTIVE_DATE ON nanny_rate (user_id, nanny_snapshot_key, effective_from)');

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
                CONSTRAINT FK_6113A84516497E83 FOREIGN KEY (nanny_id) REFERENCES nanny (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6113A845B5B48B91 ON care (public_id)');
        $this->addSql('CREATE INDEX IDX_6113A845A76ED395 ON care (user_id)');
        $this->addSql('CREATE INDEX IDX_6113A84516497E83 ON care (nanny_id)');
        $this->addSql('CREATE INDEX IDX_CARE_USER_NANNY_DATE ON care (user_id, nanny_id, date)');
        $this->addSql('CREATE INDEX IDX_CARE_USER_NANNY_SNAPSHOT_MONTH ON care (user_id, nanny_snapshot_key, month)');
        $this->addSql('CREATE INDEX IDX_CARE_USER_MONTH ON care (user_id, month)');

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
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C193BA3EB5B48B91 ON care_template (public_id)');
        $this->addSql('CREATE INDEX IDX_C193BA3EA76ED395 ON care_template (user_id)');
        $this->addSql('CREATE INDEX IDX_C193BA3E16497E83 ON care_template (nanny_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_TEMPLATE_USER_NANNY_NAME ON care_template (user_id, nanny_id, name)');

        $this->addSql(<<<'SQL'
            CREATE TABLE monthly_payment (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                user_id INTEGER NOT NULL,
                nanny_id INTEGER DEFAULT NULL,
                public_id VARCHAR(26) NOT NULL,
                nanny_display_name VARCHAR(255) NOT NULL,
                nanny_snapshot_key VARCHAR(26) DEFAULT NULL,
                totals_hours NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                total_meals INTEGER DEFAULT 0 NOT NULL,
                amount_hours NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                amount_meals NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                total_amount NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                month VARCHAR(7) NOT NULL,
                is_paid BOOLEAN DEFAULT 0 NOT NULL,
                paid_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT FK_617D3FA6A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_617D3FA616497E83 FOREIGN KEY (nanny_id) REFERENCES nanny (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_617D3FA6B5B48B91 ON monthly_payment (public_id)');
        $this->addSql('CREATE INDEX IDX_617D3FA6A76ED395 ON monthly_payment (user_id)');
        $this->addSql('CREATE INDEX IDX_617D3FA616497E83 ON monthly_payment (nanny_id)');
        $this->addSql('CREATE INDEX IDX_PAYMENT_USER_MONTH ON monthly_payment (user_id, month)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PAYMENT_USER_NANNY_MONTH ON monthly_payment (user_id, nanny_id, month)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PAYMENT_USER_NANNY_SNAPSHOT_MONTH ON monthly_payment (user_id, nanny_snapshot_key, month)');

        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                body CLOB NOT NULL,
                headers CLOB NOT NULL,
                queue_name VARCHAR(190) NOT NULL,
                created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
                ,
                available_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
                ,
                delivered_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'This migration is generated for SQLite.');

        $this->addSql('PRAGMA foreign_keys = OFF');
        foreach ([
            'care',
            'care_template',
            'monthly_payment',
            'nanny_assignment',
            'nanny_rate',
            'nanny',
            'user',
            'messenger_messages',
        ] as $table) {
            $this->addSql(sprintf('DROP TABLE IF EXISTS %s', $table));
        }
        $this->addSql('PRAGMA foreign_keys = ON');
    }
}
