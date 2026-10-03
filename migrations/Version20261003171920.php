<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261003171920 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE fuel_station (id UUID NOT NULL, name VARCHAR(160) NOT NULL, brand VARCHAR(80) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, location geometry(Geometry,4326) NOT NULL, h3_cell VARCHAR(16) NOT NULL, source VARCHAR(32) NOT NULL, external_id VARCHAR(64) DEFAULT NULL, fuel_types JSON NOT NULL, availability JSON NOT NULL, last_confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, confirmation_count INT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_fuel_station_h3 ON fuel_station (h3_cell)');
        $this->addSql('CREATE UNIQUE INDEX uniq_fuel_station_external ON fuel_station (source, external_id)');
        $this->addSql('ALTER TABLE incident ADD scope VARCHAR(8) DEFAULT \'area\' NOT NULL');
        $this->addSql('ALTER TABLE incident ADD poi_kind VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE incident ADD poi_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE incident ADD poi_name VARCHAR(160) DEFAULT NULL');
        $this->addSql('ALTER TABLE incident ADD fuel_types JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE report ADD poi_kind VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD poi_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD poi_name VARCHAR(160) DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD fuel_types JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE shelter ADD external_id VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE shelter ADD availability VARCHAR(16) DEFAULT \'unknown\' NOT NULL');
        $this->addSql('ALTER TABLE shelter ADD region JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE verification_request ADD poi_kind VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE verification_request ADD poi_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE verification_request ADD poi_name VARCHAR(160) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE fuel_station');
        $this->addSql('ALTER TABLE incident DROP scope');
        $this->addSql('ALTER TABLE incident DROP poi_kind');
        $this->addSql('ALTER TABLE incident DROP poi_id');
        $this->addSql('ALTER TABLE incident DROP poi_name');
        $this->addSql('ALTER TABLE incident DROP fuel_types');
        $this->addSql('ALTER TABLE report DROP poi_kind');
        $this->addSql('ALTER TABLE report DROP poi_id');
        $this->addSql('ALTER TABLE report DROP poi_name');
        $this->addSql('ALTER TABLE report DROP fuel_types');
        $this->addSql('ALTER TABLE shelter DROP external_id');
        $this->addSql('ALTER TABLE shelter DROP availability');
        $this->addSql('ALTER TABLE shelter DROP region');
        $this->addSql('ALTER TABLE verification_request DROP poi_kind');
        $this->addSql('ALTER TABLE verification_request DROP poi_id');
        $this->addSql('ALTER TABLE verification_request DROP poi_name');
    }
}
