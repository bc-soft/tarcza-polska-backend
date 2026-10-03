<?php

declare(strict_types=1);

namespace App\Incident\Entity;

use App\Incident\Enum\IncidentEventType;
use App\Incident\Repository\IncidentEventRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Append-only history of an incident: when it was detected, asked about, confirmed, how its area evolved.
 */
#[ORM\Entity(repositoryClass: IncidentEventRepository::class)]
#[ORM\Table(name: 'incident_event')]
#[ORM\Index(columns: ['incident_id', 'created_at'], name: 'idx_incident_event_incident_created')]
class IncidentEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Incident::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Incident $incident;

    #[ORM\Column(length: 32, enumType: IncidentEventType::class)]
    private IncidentEventType $type;

    /** @var array<string, scalar|null> small, type-specific facts (counts, levels, ids); never raw positions */
    #[ORM\Column(type: Types::JSON)]
    private array $payload;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /** @param array<string, scalar|null> $payload */
    public function __construct(Incident $incident, IncidentEventType $type, array $payload = [])
    {
        $this->id = Uuid::v7();
        $this->incident = $incident;
        $this->type = $type;
        $this->payload = $payload;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getIncident(): Incident
    {
        return $this->incident;
    }

    public function getType(): IncidentEventType
    {
        return $this->type;
    }

    /** @return array<string, scalar|null> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
