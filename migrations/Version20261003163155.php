<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261003163155 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE report_photo (id UUID NOT NULL, path VARCHAR(255) NOT NULL, mime VARCHAR(64) NOT NULL, width INT NOT NULL, height INT NOT NULL, bytes INT NOT NULL, sha256 VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, analyzed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, analyzer VARCHAR(64) DEFAULT NULL, relevant BOOLEAN DEFAULT NULL, matches_type BOOLEAN DEFAULT NULL, description TEXT DEFAULT NULL, unsafe BOOLEAN DEFAULT false NOT NULL, unsafe_reason VARCHAR(255) DEFAULT NULL, analysis_confidence DOUBLE PRECISION DEFAULT NULL, report_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_report_photo_report_created ON report_photo (report_id, created_at)');
        $this->addSql('CREATE INDEX IDX_3EAB83614BD2A4C0 ON report_photo (report_id)');
        $this->addSql('ALTER TABLE report_photo ADD CONSTRAINT FK_3EAB83614BD2A4C0 FOREIGN KEY (report_id) REFERENCES report (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report_photo DROP CONSTRAINT FK_3EAB83614BD2A4C0');
        $this->addSql('DROP TABLE report_photo');
    }
}
