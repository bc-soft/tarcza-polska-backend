<?php

declare(strict_types=1);

namespace App\Incident\MessageHandler;

use App\Incident\Enum\IncidentStatus;
use App\Incident\Message\IncidentUpdated;
use App\Incident\Message\ResolveIncident;
use App\Incident\Repository\IncidentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class ResolveIncidentHandler
{
    public function __construct(
        private IncidentRepository $incidents,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(ResolveIncident $command): void
    {
        $incident = $this->incidents->find(Uuid::fromString($command->incidentId));
        if (null === $incident || IncidentStatus::Resolved === $incident->getStatus()) {
            return;
        }
        $incident->setStatus(IncidentStatus::Resolved);
        $this->em->flush();
        $this->bus->dispatch(new IncidentUpdated($command->incidentId, 'resolved'));
    }
}
