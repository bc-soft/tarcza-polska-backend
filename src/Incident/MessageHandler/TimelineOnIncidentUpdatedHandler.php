<?php

declare(strict_types=1);

namespace App\Incident\MessageHandler;

use App\Incident\Entity\Incident;
use App\Incident\Enum\IncidentEventType;
use App\Incident\Message\IncidentUpdated;
use App\Incident\Repository\IncidentRepository;
use App\Incident\Service\IncidentTimeline;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Turns incident change notifications into history entries: area moves, closed waves, research, sources, closure.
 * (Creation, attached reports and started waves are recorded synchronously by the services that cause them.).
 */
#[AsMessageHandler]
final readonly class TimelineOnIncidentUpdatedHandler
{
    public function __construct(
        private IncidentRepository $incidents,
        private IncidentTimeline $timeline,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(IncidentUpdated $event): void
    {
        $incident = $this->incidents->find(Uuid::fromString($event->incidentId));
        if (null === $incident) {
            return;
        }

        $written = match ($event->reason) {
            'verification_response' => $this->timeline->recordAreaIfChanged($incident),
            'wave_closed' => $this->waveClosed($incident),
            'research_completed' => $this->recordAlways($incident, IncidentEventType::ResearchCompleted, ['hasSummary' => null !== $incident->getAiSummary()]),
            'external_source' => $this->recordAlways($incident, IncidentEventType::SourceAdded),
            'resolved' => $this->recordAlways($incident, IncidentEventType::Resolved, ['resolution' => $incident->getResolution()?->value]),
            default => false,
        };

        if ($written) {
            $this->em->flush();
        }
    }

    private function waveClosed(Incident $incident): bool
    {
        $this->timeline->record($incident, IncidentEventType::WaveClosed, ['ring' => $incident->getCurrentRing()]);
        $this->timeline->recordAreaIfChanged($incident);

        return true;
    }

    /** @param array<string, scalar|null> $payload */
    private function recordAlways(Incident $incident, IncidentEventType $type, array $payload = []): bool
    {
        $this->timeline->record($incident, $type, $payload);

        return true;
    }
}
