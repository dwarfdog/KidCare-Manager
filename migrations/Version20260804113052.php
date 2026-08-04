<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260804113052 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rattache course_product_alias a un magasin (course_store) : un meme produit peut porter un libelle different d\'un magasin a l\'autre. Les alias existants sont retro-associes au magasin du dernier ticket confirme de leur utilisateur (seule information disponible retroactivement).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__course_product_alias AS SELECT id, user_id, product_id, public_id, normalized_label, created_at FROM course_product_alias');
        $this->addSql('DROP TABLE course_product_alias');
        $this->addSql('CREATE TABLE course_product_alias (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, product_id INTEGER NOT NULL, store_id INTEGER NOT NULL, public_id VARCHAR(26) NOT NULL, normalized_label VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_83BD5411A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_83BD54114584665A FOREIGN KEY (product_id) REFERENCES course_product (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_83BD5411B092A811 FOREIGN KEY (store_id) REFERENCES course_store (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        // Retro-association : le magasin du dernier ticket confirme de l'utilisateur (seule donnee disponible,
        // l'ancien modele n'enregistrait pas le magasin sur l'alias). Une ligne sans ticket confirme avec
        // magasin ne peut pas exister (un alias n'est cree qu'a la confirmation, qui exige un magasin).
        $this->addSql('INSERT INTO course_product_alias (id, user_id, product_id, store_id, public_id, normalized_label, created_at) SELECT t.id, t.user_id, t.product_id, (SELECT r.store_id FROM course_receipt r WHERE r.user_id = t.user_id AND r.store_id IS NOT NULL ORDER BY r.confirmed_at DESC LIMIT 1), t.public_id, t.normalized_label, t.created_at FROM __temp__course_product_alias t');
        $this->addSql('DROP TABLE __temp__course_product_alias');
        $this->addSql('CREATE INDEX IDX_83BD54114584665A ON course_product_alias (product_id)');
        $this->addSql('CREATE INDEX IDX_83BD5411A76ED395 ON course_product_alias (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_83BD5411B5B48B91 ON course_product_alias (public_id)');
        $this->addSql('CREATE INDEX IDX_83BD5411B092A811 ON course_product_alias (store_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_COURSE_PRODUCT_ALIAS_USER_STORE_LABEL ON course_product_alias (user_id, store_id, normalized_label)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__course_product_alias AS SELECT id, user_id, product_id, public_id, normalized_label, created_at FROM course_product_alias');
        $this->addSql('DROP TABLE course_product_alias');
        $this->addSql('CREATE TABLE course_product_alias (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, product_id INTEGER NOT NULL, public_id VARCHAR(26) NOT NULL, normalized_label VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_83BD5411A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_83BD54114584665A FOREIGN KEY (product_id) REFERENCES course_product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO course_product_alias (id, user_id, product_id, public_id, normalized_label, created_at) SELECT id, user_id, product_id, public_id, normalized_label, created_at FROM __temp__course_product_alias');
        $this->addSql('DROP TABLE __temp__course_product_alias');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_83BD5411B5B48B91 ON course_product_alias (public_id)');
        $this->addSql('CREATE INDEX IDX_83BD5411A76ED395 ON course_product_alias (user_id)');
        $this->addSql('CREATE INDEX IDX_83BD54114584665A ON course_product_alias (product_id)');
        $this->addSql('CREATE INDEX IDX_COURSE_PRODUCT_ALIAS_USER_LABEL ON course_product_alias (user_id, normalized_label)');
    }
}
