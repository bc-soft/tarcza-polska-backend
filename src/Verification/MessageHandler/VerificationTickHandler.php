<?php

declare(strict_types=1);

namespace App\Verification\MessageHandler;

use App\Incident\Enum\IncidentResolution;
use App\Incident\Enum\IncidentStatus;
use App\Incident\Message\IncidentUpdated;
use App\Incident\Repository\IncidentRepository;
use App\Incident\Service\AreaCalculator;
use App\Verification\Message\VerificationTick;
use App\Verification\Repository\VerificationWaveRepository;
use App\Verification\Service\VerificationScheduler;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Every 20 s: close waves whose TTL passed, recompute, plan the next ring, decay stale incidents.
 */
#[AsMessageHandler]
final readonly class VerificationTickHandler
{
    private const int STALE_AFTER_HOURS = 12;

    public function __construct(
        private VerificationWaveRepository $waves,
        private IncidentRepository $incidents,
        private VerificationScheduler $scheduler,
        private AreaCalculator $areaCalculator,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private int $clusterWindowMinutes,
    ) {
    }

    public function __invoke(VerificationTick $tick): void
    {
        foreach ($this->waves->findExpiredOpen() as $wave) {
            $wave->close();
            $incident = $wave->getIncident();
            $this->areaCalculator->recompute($incident);
            $this->em->flush();

            $this->bus->dispatch(new IncidentUpdated($incident->getId()->toRfc4122(), 'wave_closed'));
            $this->scheduler->planNextWave($incident);
        }

        // Decay: nothing happened for a long time -> resolve automatically.
        $cutoff = new DateTimeImmutable(\sprintf('-%d minutes', max($this->clusterWindowMinutes * 2, self::STALE_AFTER_HOURS * 60)));
        foreach ($this->incidents->findOpen() as $incident) {
            if ($incident->getLastActivityAt() < $cutoff) {
                $incident->setStatus(IncidentStatus::Resolved, IncidentResolution::Expired);
                $this->em->flush();
                $this->bus->dispatch(new IncidentUpdated($incident->getId()->toRfc4122(), 'resolved'));
            }
        }
    }
}
