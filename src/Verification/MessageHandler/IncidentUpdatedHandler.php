<?php

declare(strict_types=1);

namespace App\Verification\MessageHandler;

use App\Incident\Message\IncidentUpdated;
use App\Incident\Repository\IncidentRepository;
use App\Verification\Message\ScheduleVerificationWave;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Starts active verification as soon as a cluster looks real (>= MIN_REPORTS independent reports).
 */
#[AsMessageHandler]
final readonly class IncidentUpdatedHandler
{
    private const int MIN_REPORTS = 2;

    public function __construct(
        private IncidentRepository $incidents,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(IncidentUpdated $event): void
    {
        if (!\in_array($event->reason, ['incident_created', 'report_attached'], true)) {
            return;
        }
        $incident = $this->incidents->find(Uuid::fromString($event->incidentId));
        if (null === $incident || !$incident->getStatus()->isOpen() || -1 !== $incident->getCurrentRing()) {
            return;
        }
        if ($incident->getReports()->count() < self::MIN_REPORTS) {
            return;
        }

        $this->bus->dispatch(new ScheduleVerificationWave($event->incidentId));
    }
}
