<?php

declare(strict_types=1);

namespace App\Identity\MessageHandler;

use App\Identity\Service\ReputationUpdater;
use App\Incident\Enum\IncidentResolution;
use App\Incident\Message\IncidentResolved;
use App\Incident\Repository\IncidentRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class IncidentResolvedHandler
{
    public function __construct(
        private IncidentRepository $incidents,
        private ReputationUpdater $reputation,
    ) {
    }

    public function __invoke(IncidentResolved $event): void
    {
        $resolution = IncidentResolution::tryFrom($event->resolution);
        $incident = $this->incidents->find(Uuid::fromString($event->incidentId));
        if (null === $resolution || null === $incident) {
            return;
        }

        $this->reputation->applyResolution($incident, $resolution);
    }
}
