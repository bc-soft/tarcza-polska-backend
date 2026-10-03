<?php

declare(strict_types=1);

namespace App\Confidence\MessageHandler;

use App\Confidence\Message\IncidentConfidenceUpdated;
use App\Confidence\Service\IncidentConfidenceUpdater;
use App\Incident\Enum\ConfidenceLevel;
use App\Incident\Message\IncidentUpdated;
use App\Incident\Repository\IncidentRepository;
use App\Intelligence\Message\ResearchIncident;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Recalculates confidence on every incident change and triggers AI research once the cluster becomes LIKELY.
 */
#[AsMessageHandler]
final readonly class IncidentUpdatedHandler
{
    public function __construct(
        private IncidentRepository $incidents,
        private IncidentConfidenceUpdater $updater,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(IncidentUpdated $event): void
    {
        $incident = $this->incidents->find(Uuid::fromString($event->incidentId));
        if (null === $incident) {
            return;
        }

        $result = $this->updater->update($incident);
        $this->em->flush();

        $this->bus->dispatch(new IncidentConfidenceUpdated(
            $event->incidentId,
            $result['previous']->value,
            $result['current']->value,
            $result['score'],
            $event->reason,
        ));

        if ($result['current']->atLeast(ConfidenceLevel::Likely) && null === $incident->getResearchedAt() && $incident->getStatus()->isOpen()) {
            $this->bus->dispatch(new ResearchIncident($event->incidentId));
        }
    }
}
