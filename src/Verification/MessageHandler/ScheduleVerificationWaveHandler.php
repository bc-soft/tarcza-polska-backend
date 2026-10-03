<?php

declare(strict_types=1);

namespace App\Verification\MessageHandler;

use App\Incident\Repository\IncidentRepository;
use App\Verification\Message\ScheduleVerificationWave;
use App\Verification\Service\VerificationScheduler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class ScheduleVerificationWaveHandler
{
    public function __construct(
        private IncidentRepository $incidents,
        private VerificationScheduler $scheduler,
    ) {
    }

    public function __invoke(ScheduleVerificationWave $command): void
    {
        $incident = $this->incidents->find(Uuid::fromString($command->incidentId));
        if (null === $incident) {
            return;
        }
        $this->scheduler->planNextWave($incident);
    }
}
