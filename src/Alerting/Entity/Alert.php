<?php

declare(strict_types=1);

namespace App\Alerting\Entity;

use App\Alerting\Repository\AlertRepository;
use App\Incident\Entity\Incident;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Operator message bound to an area (INFORM). Delivered by push to devices inside and shown on the map.
 */
#[ORM\Entity(repositoryClass: AlertRepository::class)]
#[ORM\Table(name: 'alert')]
#[ORM\Index(columns: ['expires_at'], name: 'idx_alert_expires')]
class Alert
{
    public const string SEVERITY_INFO = 'info';
    public const string SEVERITY_WARNING = 'warning';
    public const string SEVERITY_DANGER = 'danger';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Incident::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Incident $incident = null;

    #[ORM\Column(length: 160)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(length: 16)]
    private string $severity;

    /** @var array<string, mixed> GeoJSON Polygon / MultiPolygon */
    #[ORM\Column(type: 'geo_geometry')]
    private array $area;

    #[ORM\Column(length: 180)]
    private string $createdBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $deliveredCount = 0;

    /** @param array<string, mixed> $area */
    public function __construct(string $title, string $body, string $severity, array $area, string $createdBy, DateTimeImmutable $expiresAt, ?Incident $incident = null)
    {
        $this->id = Uuid::v7();
        $this->title = $title;
        $this->body = $body;
        $this->severity = $severity;
        $this->area = $area;
        $this->createdBy = $createdBy;
        $this->createdAt = new DateTimeImmutable();
        $this->expiresAt = $expiresAt;
        $this->incident = $incident;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getIncident(): ?Incident
    {
        return $this->incident;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    /** @return array<string, mixed> */
    public function getArea(): array
    {
        return $this->area;
    }

    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isActive(): bool
    {
        return $this->expiresAt > new DateTimeImmutable();
    }

    public function getDeliveredCount(): int
    {
        return $this->deliveredCount;
    }

    public function setDeliveredCount(int $n): void
    {
        $this->deliveredCount = $n;
    }
}
