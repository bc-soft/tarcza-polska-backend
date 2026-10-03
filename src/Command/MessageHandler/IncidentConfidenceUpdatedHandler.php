<?php

declare(strict_types=1);

namespace App\Command\MessageHandler;

use App\Command\Service\IncidentBroadcaster;
use App\Confidence\Message\IncidentConfidenceUpdated;
use App\Incident\Repository\IncidentRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class IncidentConfidenceUpdatedHandler
{
    public function __construct(
        private IncidentRepository $incidents,
        private IncidentBroadcaster $broadcaster,
    ) {
    }

    public function __invoke(IncidentConfidenceUpdated $event): void
    {
        $incident = $this->incidents->find(Uuid::fromString($event->incidentId));
        if (null === $incident) {
            return;
        }
        $this->broadcaster->broadcast($incident, $event->reason);
    }
}
