<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261003145237 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Device: location_refresh_enabled preference and last_location_refresh_at (reminder push)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE device ADD location_refresh_enabled BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE device ADD last_location_refresh_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE device DROP location_refresh_enabled');
        $this->addSql('ALTER TABLE device DROP last_location_refresh_at');
    }
}
