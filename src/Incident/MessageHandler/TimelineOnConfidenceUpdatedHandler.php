<?php

declare(strict_types=1);

namespace App\Incident\MessageHandler;

use App\Confidence\Message\IncidentConfidenceUpdated;
use App\Incident\Enum\IncidentEventType;
use App\Incident\Repository\IncidentRepository;
use App\Incident\Service\IncidentTimeline;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class TimelineOnConfidenceUpdatedHandler
{
    public function __construct(
        private IncidentRepository $incidents,
        private IncidentTimeline $timeline,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(IncidentConfidenceUpdated $event): void
    {
        if (!$event->levelChanged()) {
            return;
        }
        $incident = $this->incidents->find(Uuid::fromString($event->incidentId));
        if (null === $incident) {
            return;
        }

        $this->timeline->record($incident, IncidentEventType::ConfidenceChanged, [
            'from' => $event->previousLevel,
            'to' => $event->currentLevel,
            'score' => round($event->score, 3),
            'reason' => $event->reason,
        ]);
        $this->em->flush();
    }
}
