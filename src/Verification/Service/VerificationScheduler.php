<?php

declare(strict_types=1);

namespace App\Verification\Service;

use App\Fuel\Enum\FuelType;
use App\Identity\Repository\DeviceRepository;
use App\Incident\Entity\Incident;
use App\Incident\Enum\IncidentEventType;
use App\Incident\Enum\IncidentStatus;
use App\Incident\Service\AreaCalculator;
use App\Incident\Service\IncidentTimeline;
use App\Notification\PushMessage;
use App\Notification\PushSenderInterface;
use App\Shared\Geo\H3;
use App\Shared\Poi\PoiRef;
use App\Shared\Poi\PoiRegistry;
use App\Verification\Entity\VerificationRequest;
use App\Verification\Entity\VerificationWave;
use App\Verification\Repository\VerificationWaveRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Active Crowd Verification engine.
 *
 * Area incidents (power, water, roads, threats):
 *  wave -1 -> none yet
 *  wave 0  -> core: center cell + its 6 neighbours
 *  wave k  -> frontier: unknown cells touching the positive area, capped at $maxRing from the center
 *  A cell asked twice with no answer is treated as "no data" and no longer blocks the frontier.
 *
 * Point incidents (fuel stations, shelters):
 *  wave 0  -> people standing at the reported object: "is there fuel at station X?"
 *  wave k  -> people at the k-th batch of neighbouring objects of the same kind, nearest first.
 *  Answers update each object's own status; only answers about the reported object feed the incident.
 */
final readonly class VerificationScheduler
{
    private const int GIVE_UP_AFTER_ASKS = 2;
    private const int POIS_PER_RING = 3;

    public function __construct(
        private VerificationWaveRepository $waves,
        private DeviceRepository $devices,
        private AreaCalculator $areaCalculator,
        private IncidentTimeline $timeline,
        private PoiRegistry $pois,
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

        return $incident->isPointScoped() ? $this->planPointWave($incident) : $this->planAreaWave($incident);
    }

    private function planAreaWave(Incident $incident): ?VerificationWave
    {
        $ring = $incident->getCurrentRing() + 1;
        $targets = $this->targetCells($incident, $ring);

        if ([] === $targets) {
            return $this->finish($incident, 'no more cells to verify, boundary stable');
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

        $this->commitWave($incident, $wave, $ring, $requests, ['cells' => \count($targets)]);

        foreach ($requests as $request) {
            $this->push->send([$request->getDevice()], PushMessage::verification(
                $request->getId()->toRfc4122(),
                $question,
                $request->getExpiresAt(),
                ['incidentId' => $incident->getId()->toRfc4122(), 'incidentType' => $incident->getType()->value],
            ));
        }

        $this->logger->info('Incident {id}: wave ring {ring} -> {cells} cells, {devices} devices', [
            'id' => $incident->getId()->toRfc4122(), 'ring' => $ring, 'cells' => \count($targets), 'devices' => \count($requests),
        ]);

        return $wave;
    }

    private function planPointWave(Incident $incident): ?VerificationWave
    {
        $origin = $incident->poiRef();
        if (null === $origin) {
            return $this->finish($incident, 'point incident without an object');
        }
        $ring = $incident->getCurrentRing() + 1;
        $targets = $this->targetPois($incident, $origin, $ring);
        if ([] === $targets) {
            return $this->finish($incident, 'no more objects to ask about');
        }

        $wave = new VerificationWave($incident, $ring, array_map(static fn (PoiRef $p) => $p->id->toRfc4122(), $targets), $this->waveTtlSeconds);
        $this->em->persist($wave);

        $detail = FuelType::labels($incident->getFuelTypes());
        $requests = [];
        $perPoi = [];
        foreach ($targets as $poi) {
            $question = $incident->getType()->pointVerificationQuestion($poi->name, $detail);
            $kind = $poi->kind;
            $candidates = $this->devices->findNearPoint($poi->location, $kind->presenceRadiusMeters(), $this->devicesPerCell, $this->cooldownMinutes);
            $perPoi[$poi->name] = \count($candidates);
            foreach ($candidates as $device) {
                $request = new VerificationRequest($wave, $device, $device->getH3Cell() ?? $incident->getCenterCell(), $question, $poi);
                $this->em->persist($request);
                $device->markAsked();
                $requests[] = $request;
            }
        }
        $incident->cell($incident->getCenterCell(), 0)->addAsked(\count($requests));

        $this->commitWave($incident, $wave, $ring, $requests, [
            'pois' => \count($targets),
            'poiNames' => implode(' | ', array_map(static fn (PoiRef $p) => $p->name, $targets)),
        ]);

        foreach ($requests as $request) {
            $this->push->send([$request->getDevice()], PushMessage::verification(
                $request->getId()->toRfc4122(),
                $request->getQuestion(),
                $request->getExpiresAt(),
                [
                    'incidentId' => $incident->getId()->toRfc4122(),
                    'incidentType' => $incident->getType()->value,
                    'poiKind' => (string) $request->getPoiKind()?->value,
                    'poiId' => (string) $request->getPoiId()?->toRfc4122(),
                    'poiName' => (string) $request->getPoiName(),
                ],
            ));
        }

        $this->logger->info('Incident {id}: point wave ring {ring} -> {pois} objects, {devices} devices {perPoi}', [
            'id' => $incident->getId()->toRfc4122(), 'ring' => $ring, 'pois' => \count($targets), 'devices' => \count($requests), 'perPoi' => json_encode($perPoi),
        ]);

        return $wave;
    }

    /**
     * @param list<VerificationRequest>  $requests
     * @param array<string, scalar|null> $extra
     */
    private function commitWave(Incident $incident, VerificationWave $wave, int $ring, array $requests, array $extra): void
    {
        $wave->setRequestsSent(\count($requests));
        $incident->setCurrentRing($ring);
        $incident->setStatus(IncidentStatus::Verifying);
        $incident->touch();
        $this->timeline->record($incident, IncidentEventType::WaveStarted, ['ring' => $ring, 'devices' => \count($requests)] + $extra);
        $this->em->flush();
    }

    private function finish(Incident $incident, string $why): null
    {
        if (IncidentStatus::Verifying === $incident->getStatus()) {
            $incident->setStatus(IncidentStatus::Active);
            $this->em->flush();
        }
        $this->logger->info('Incident {id}: {why}', ['id' => $incident->getId()->toRfc4122(), 'why' => $why]);

        return null;
    }

    /** @return list<PoiRef> */
    private function targetPois(Incident $incident, PoiRef $origin, int $ring): array
    {
        if (0 === $ring) {
            return [$origin];
        }
        if ($ring > $this->maxRing) {
            return [];
        }
        $locator = $this->pois->locator($origin->kind);
        $neighbours = $locator->nearby($origin->location, $origin->kind->verificationRadiusMeters(), self::POIS_PER_RING * $this->maxRing, $origin->id);

        return \array_slice($neighbours, ($ring - 1) * self::POIS_PER_RING, self::POIS_PER_RING);
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
