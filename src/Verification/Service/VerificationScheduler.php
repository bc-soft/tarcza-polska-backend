<?php

declare(strict_types=1);

namespace App\Verification\Service;

use App\Identity\Repository\DeviceRepository;
use App\Incident\Entity\Incident;
use App\Incident\Enum\IncidentEventType;
use App\Incident\Enum\IncidentStatus;
use App\Incident\Service\AreaCalculator;
use App\Incident\Service\IncidentTimeline;
use App\Notification\PushMessage;
use App\Notification\PushSenderInterface;
use App\Shared\Geo\H3;
use App\Verification\Entity\VerificationRequest;
use App\Verification\Entity\VerificationWave;
use App\Verification\Repository\VerificationWaveRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Active Crowd Verification engine.
 *
 *  wave -1 -> none yet
 *  wave 0  -> core: center cell + its 6 neighbours
 *  wave k  -> frontier: unknown cells touching the positive area, capped at $maxRing from the center
 *
 * A cell asked twice with no answer is treated as "no data" and no longer blocks the frontier.
 */
final readonly class VerificationScheduler
{
    private const int GIVE_UP_AFTER_ASKS = 2;

    public function __construct(
        private VerificationWaveRepository $waves,
        private DeviceRepository $devices,
        private AreaCalculator $areaCalculator,
        private IncidentTimeline $timeline,
        private PushSenderInterface $push,
        private EntityManagerInterface $em,
        private H3 $h3,
        private LoggerInterface $logger,
        private int $waveTtlSeconds,
        private int $maxRing,
        private int $devicesPerCell,
        private int $cooldownMinutes,
    ) {
    }

    /** @return VerificationWave|null the wave that was sent, or null when nothing (more) to ask */
    public function planNextWave(Incident $incident): ?VerificationWave
    {
        if (!$incident->getStatus()->isOpen()) {
            return null;
        }
        if (null !== $this->waves->findOpenFor($incident)) {
            return null; // one wave at a time
        }

        $ring = $incident->getCurrentRing() + 1;
        $targets = $this->targetCells($incident, $ring);

        if ([] === $targets) {
            // Frontier exhausted: boundary is as good as it gets with the people available.
            if (IncidentStatus::Verifying === $incident->getStatus()) {
                $incident->setStatus(IncidentStatus::Active);
            }
            $this->logger->info('Incident {id}: no more cells to verify, boundary stable', ['id' => $incident->getId()->toRfc4122()]);

            return null;
        }

        $candidates = $this->devices->findVerificationCandidates($targets, $this->devicesPerCell, $this->cooldownMinutes);

        $wave = new VerificationWave($incident, $ring, $targets, $this->waveTtlSeconds);
        $this->em->persist($wave);

        $question = $incident->getType()->verificationQuestion();
        $requests = [];
        foreach ($candidates as $device) {
            $cell = (string) $device->getH3Cell();
            $request = new VerificationRequest($wave, $device, $cell, $question);
            $this->em->persist($request);
            $device->markAsked();
            $requests[] = $request;
        }

        // Register every targeted cell on the incident (so the frontier logic can see asked-but-silent cells).
        foreach ($targets as $cell) {
            $incident->cell($cell, $this->h3->distance($incident->getCenterCell(), $cell))->addAsked();
        }

        $wave->setRequestsSent(\count($requests));
        $incident->setCurrentRing($ring);
        $incident->setStatus(IncidentStatus::Verifying);
        $incident->touch();
        $this->timeline->record($incident, IncidentEventType::WaveStarted, [
            'ring' => $ring,
            'cells' => \count($targets),
            'devices' => \count($requests),
        ]);
        $this->em->flush();

        foreach ($requests as $request) {
            $this->push->send([$request->getDevice()], PushMessage::verification(
                $request->getId()->toRfc4122(),
                $question,
                $request->getExpiresAt(),
                ['incidentId' => $incident->getId()->toRfc4122(), 'incidentType' => $incident->getType()->value],
            ));
        }

        $this->logger->info('Incident {id}: wave ring {ring} -> {cells} cells, {devices} devices', [
            'id' => $incident->getId()->toRfc4122(),
            'ring' => $ring,
            'cells' => \count($targets),
            'devices' => \count($requests),
        ]);

        return $wave;
    }

    /** @return list<string> */
    private function targetCells(Incident $incident, int $ring): array
    {
        if (0 === $ring) {
            return $this->h3->gridDisk($incident->getCenterCell(), 1);
        }
        if ($ring > $this->maxRing) {
            return [];
        }

        $cells = $incident->getCells();
        $center = $incident->getCenterCell();

        return array_values(array_filter(
            $this->areaCalculator->frontier($incident),
            function (string $cell) use ($cells, $center): bool {
                $known = $cells->get($cell);
                if (null !== $known && $known->getAskedCount() >= self::GIVE_UP_AFTER_ASKS && !$known->hasResponses()) {
                    return false;
                }

                return $this->h3->distance($center, $cell) <= $this->maxRing;
            },
        ));
    }
}
