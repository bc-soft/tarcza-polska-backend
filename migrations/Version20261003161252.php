<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261003161252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE incident_event (id UUID NOT NULL, type VARCHAR(32) NOT NULL, payload JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, incident_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_incident_event_incident_created ON incident_event (incident_id, created_at)');
        $this->addSql('CREATE INDEX IDX_609AA8CD59E53FB9 ON incident_event (incident_id)');
        $this->addSql('ALTER TABLE incident_event ADD CONSTRAINT FK_609AA8CD59E53FB9 FOREIGN KEY (incident_id) REFERENCES incident (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE incident ADD resolution VARCHAR(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE incident_event DROP CONSTRAINT FK_609AA8CD59E53FB9');
        $this->addSql('DROP TABLE incident_event');
        $this->addSql('ALTER TABLE incident DROP resolution');
    }
}
