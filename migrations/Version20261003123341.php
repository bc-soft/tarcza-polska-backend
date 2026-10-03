<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261003123341 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: identity, reporting, incidents (PostGIS + H3 cells), verification, intelligence, alerting, shelters, audit, simulation';
    }

    public function up(Schema $schema): void
    {
        // Spatial stack. Requires a role allowed to create extensions (true for the dev container and most managed Postgres offers).
        $this->addSql('CREATE EXTENSION IF NOT EXISTS postgis');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS postgis_raster');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS h3');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS h3_postgis');
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE alert (id UUID NOT NULL, title VARCHAR(160) NOT NULL, body TEXT NOT NULL, severity VARCHAR(16) NOT NULL, area geometry(Geometry,4326) NOT NULL, created_by VARCHAR(180) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_count INT DEFAULT 0 NOT NULL, incident_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_alert_expires ON alert (expires_at)');
        $this->addSql('CREATE INDEX IDX_17FD46C159E53FB9 ON alert (incident_id)');
        $this->addSql('CREATE TABLE audit_log (id UUID NOT NULL, actor VARCHAR(180) NOT NULL, action VARCHAR(64) NOT NULL, subject_type VARCHAR(64) NOT NULL, subject_id VARCHAR(64) NOT NULL, context JSON NOT NULL, ip VARCHAR(45) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_audit_actor_created ON audit_log (actor, created_at)');
        $this->addSql('CREATE INDEX idx_audit_subject ON audit_log (subject_type, subject_id)');
        $this->addSql('CREATE TABLE device (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, platform VARCHAR(32) DEFAULT NULL, app_version VARCHAR(32) DEFAULT NULL, push_token TEXT DEFAULT NULL, last_location geometry(Geometry,4326) DEFAULT NULL, h3_cell VARCHAR(16) DEFAULT NULL, location_updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_asked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, reputation DOUBLE PRECISION DEFAULT 1 NOT NULL, simulated BOOLEAN DEFAULT false NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_device_h3_cell ON device (h3_cell)');
        $this->addSql('CREATE INDEX idx_device_last_seen ON device (last_seen_at)');
        $this->addSql('CREATE TABLE external_source (id UUID NOT NULL, url VARCHAR(2048) NOT NULL, title VARCHAR(255) NOT NULL, publisher VARCHAR(255) DEFAULT NULL, kind VARCHAR(32) NOT NULL, credibility DOUBLE PRECISION NOT NULL, excerpt TEXT DEFAULT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, found_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, found_by VARCHAR(16) NOT NULL, incident_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_2B09E09A59E53FB9 ON external_source (incident_id)');
        $this->addSql('CREATE TABLE incident (id UUID NOT NULL, type VARCHAR(32) NOT NULL, status VARCHAR(16) NOT NULL, confidence_score DOUBLE PRECISION NOT NULL, confidence_level VARCHAR(16) NOT NULL, confidence_breakdown JSON NOT NULL, centroid geometry(Geometry,4326) NOT NULL, center_cell VARCHAR(16) NOT NULL, area geometry(Geometry,4326) DEFAULT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_activity_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, current_ring SMALLINT DEFAULT -1 NOT NULL, ai_summary TEXT DEFAULT NULL, researched_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_incident_type_status ON incident (type, status)');
        $this->addSql('CREATE INDEX idx_incident_activity ON incident (last_activity_at)');
        $this->addSql('CREATE TABLE incident_cell (id UUID NOT NULL, h3_index VARCHAR(16) NOT NULL, ring SMALLINT NOT NULL, state VARCHAR(16) NOT NULL, report_count INT DEFAULT 0 NOT NULL, yes_count INT DEFAULT 0 NOT NULL, no_count INT DEFAULT 0 NOT NULL, unknown_count INT DEFAULT 0 NOT NULL, asked_count INT DEFAULT 0 NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, incident_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_incident_cell_h3 ON incident_cell (h3_index)');
        $this->addSql('CREATE UNIQUE INDEX uniq_incident_cell ON incident_cell (incident_id, h3_index)');
        $this->addSql('CREATE INDEX IDX_218BF05D59E53FB9 ON incident_cell (incident_id)');
        $this->addSql('CREATE TABLE operator (id UUID NOT NULL, email VARCHAR(180) NOT NULL, display_name VARCHAR(120) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, organisation VARCHAR(120) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_operator_email ON operator (email)');
        $this->addSql('CREATE TABLE report (id UUID NOT NULL, type VARCHAR(32) NOT NULL, location geometry(Geometry,4326) NOT NULL, h3_cell VARCHAR(16) NOT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, weight DOUBLE PRECISION DEFAULT 1 NOT NULL, photo_path VARCHAR(255) DEFAULT NULL, device_id UUID NOT NULL, incident_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_report_type_created ON report (type, created_at)');
        $this->addSql('CREATE INDEX idx_report_h3_cell ON report (h3_cell)');
        $this->addSql('CREATE INDEX IDX_C42F778494A4C7D4 ON report (device_id)');
        $this->addSql('CREATE INDEX IDX_C42F778459E53FB9 ON report (incident_id)');
        $this->addSql('CREATE TABLE shelter (id UUID NOT NULL, name VARCHAR(160) NOT NULL, address VARCHAR(255) DEFAULT NULL, location geometry(Geometry,4326) NOT NULL, capacity INT DEFAULT NULL, status VARCHAR(16) NOT NULL, last_confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, confirmation_count INT DEFAULT 0 NOT NULL, source VARCHAR(64) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE shelter_status_report (id UUID NOT NULL, status VARCHAR(16) NOT NULL, comment TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, shelter_id UUID NOT NULL, device_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_ssr_shelter_created ON shelter_status_report (shelter_id, created_at)');
        $this->addSql('CREATE INDEX IDX_F3CC89EE54053EC0 ON shelter_status_report (shelter_id)');
        $this->addSql('CREATE INDEX IDX_F3CC89EE94A4C7D4 ON shelter_status_report (device_id)');
        $this->addSql('CREATE TABLE simulation_scenario (id UUID NOT NULL, name VARCHAR(120) NOT NULL, type VARCHAR(32) NOT NULL, center geometry(Geometry,4326) NOT NULL, radius_meters INT NOT NULL, accuracy DOUBLE PRECISION NOT NULL, unknown_rate DOUBLE PRECISION NOT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE verification_request (id UUID NOT NULL, h3_cell VARCHAR(16) NOT NULL, question VARCHAR(255) NOT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, answered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, answer VARCHAR(8) DEFAULT NULL, normalised_answer VARCHAR(8) DEFAULT NULL, wave_id UUID NOT NULL, incident_id UUID NOT NULL, device_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_vr_pending ON verification_request (device_id, answered_at, expires_at)');
        $this->addSql('CREATE INDEX IDX_20FDDF4E9461E358 ON verification_request (wave_id)');
        $this->addSql('CREATE INDEX IDX_20FDDF4E59E53FB9 ON verification_request (incident_id)');
        $this->addSql('CREATE INDEX IDX_20FDDF4E94A4C7D4 ON verification_request (device_id)');
        $this->addSql('CREATE TABLE verification_wave (id UUID NOT NULL, ring SMALLINT NOT NULL, target_cells JSON NOT NULL, requests_sent INT NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, incident_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_wave_open ON verification_wave (closed_at, expires_at)');
        $this->addSql('CREATE INDEX IDX_45305E8E59E53FB9 ON verification_wave (incident_id)');
        $this->addSql('ALTER TABLE alert ADD CONSTRAINT FK_17FD46C159E53FB9 FOREIGN KEY (incident_id) REFERENCES incident (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE external_source ADD CONSTRAINT FK_2B09E09A59E53FB9 FOREIGN KEY (incident_id) REFERENCES incident (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE incident_cell ADD CONSTRAINT FK_218BF05D59E53FB9 FOREIGN KEY (incident_id) REFERENCES incident (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F778494A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F778459E53FB9 FOREIGN KEY (incident_id) REFERENCES incident (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE shelter_status_report ADD CONSTRAINT FK_F3CC89EE54053EC0 FOREIGN KEY (shelter_id) REFERENCES shelter (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE shelter_status_report ADD CONSTRAINT FK_F3CC89EE94A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE verification_request ADD CONSTRAINT FK_20FDDF4E9461E358 FOREIGN KEY (wave_id) REFERENCES verification_wave (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE verification_request ADD CONSTRAINT FK_20FDDF4E59E53FB9 FOREIGN KEY (incident_id) REFERENCES incident (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE verification_request ADD CONSTRAINT FK_20FDDF4E94A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE verification_wave ADD CONSTRAINT FK_45305E8E59E53FB9 FOREIGN KEY (incident_id) REFERENCES incident (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE alert DROP CONSTRAINT FK_17FD46C159E53FB9');
        $this->addSql('ALTER TABLE external_source DROP CONSTRAINT FK_2B09E09A59E53FB9');
        $this->addSql('ALTER TABLE incident_cell DROP CONSTRAINT FK_218BF05D59E53FB9');
        $this->addSql('ALTER TABLE report DROP CONSTRAINT FK_C42F778494A4C7D4');
        $this->addSql('ALTER TABLE report DROP CONSTRAINT FK_C42F778459E53FB9');
        $this->addSql('ALTER TABLE shelter_status_report DROP CONSTRAINT FK_F3CC89EE54053EC0');
        $this->addSql('ALTER TABLE shelter_status_report DROP CONSTRAINT FK_F3CC89EE94A4C7D4');
        $this->addSql('ALTER TABLE verification_request DROP CONSTRAINT FK_20FDDF4E9461E358');
        $this->addSql('ALTER TABLE verification_request DROP CONSTRAINT FK_20FDDF4E59E53FB9');
        $this->addSql('ALTER TABLE verification_request DROP CONSTRAINT FK_20FDDF4E94A4C7D4');
        $this->addSql('ALTER TABLE verification_wave DROP CONSTRAINT FK_45305E8E59E53FB9');
        $this->addSql('DROP TABLE alert');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE device');
        $this->addSql('DROP TABLE external_source');
        $this->addSql('DROP TABLE incident');
        $this->addSql('DROP TABLE incident_cell');
        $this->addSql('DROP TABLE operator');
        $this->addSql('DROP TABLE report');
        $this->addSql('DROP TABLE shelter');
        $this->addSql('DROP TABLE shelter_status_report');
        $this->addSql('DROP TABLE simulation_scenario');
        $this->addSql('DROP TABLE verification_request');
        $this->addSql('DROP TABLE verification_wave');
    }
}
