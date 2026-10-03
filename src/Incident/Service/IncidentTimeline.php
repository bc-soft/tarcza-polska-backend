<?php

declare(strict_types=1);

namespace App\Incident\Service;

use App\Incident\Entity\Incident;
use App\Incident\Entity\IncidentEvent;
use App\Incident\Enum\IncidentEventType;
use App\Incident\Model\TimelineEntry;
use App\Incident\Repository\IncidentEventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The one place that appends to an incident's history. Other modules call record() through this service.
 * Persisting is the caller's responsibility (flush), so an event is stored atomically with the change it describes.
 */
final readonly class IncidentTimeline
{
    public function __construct(
        private IncidentEventRepository $events,
        private EntityManagerInterface $em,
    ) {
    }

    /** @param array<string, scalar|null> $payload */
    public function record(Incident $incident, IncidentEventType $type, array $payload = []): IncidentEvent
    {
        $event = new IncidentEvent($incident, $type, $payload);
        $this->em->persist($event);

        return $event;
    }

    /**
     * Records an area change only when the number of decided cells actually moved since the last entry.
     * Returns true when an entry was written.
     */
    public function recordAreaIfChanged(Incident $incident): bool
    {
        $t = $incident->tallies();
        $last = $this->events->findLastOfType($incident, IncidentEventType::AreaChanged);
        $previous = $last?->getPayload() ?? ['positiveCells' => 0, 'negativeCells' => 0];

        if (($previous['positiveCells'] ?? 0) === $t['positive'] && ($previous['negativeCells'] ?? 0) === $t['negative']) {
            return false;
        }

        $this->record($incident, IncidentEventType::AreaChanged, [
            'positiveCells' => $t['positive'],
            'negativeCells' => $t['negative'],
            'unknownCells' => $t['cells'] - $t['positive'] - $t['negative'],
            'yes' => $t['yes'],
            'no' => $t['no'],
        ]);

        return true;
    }

    /** @return list<TimelineEntry> oldest first */
    public function entries(Incident $incident, bool $publicOnly = false): array
    {
        return array_map(
            static fn (IncidentEvent $e) => new TimelineEntry($e->getType(), $e->getCreatedAt(), $e->getPayload()),
            $this->events->findByIncident($incident, $publicOnly),
        );
    }
}
