<?php

declare(strict_types=1);

namespace App\Simulation\Fixture;

use App\Alerting\Entity\Alert;
use App\Confidence\Service\IncidentConfidenceUpdater;
use App\Fuel\Entity\FuelStation;
use App\Fuel\Enum\FuelAvailability;
use App\Fuel\Enum\FuelType;
use App\Fuel\Repository\FuelStationRepository;
use App\Fuel\Service\FuelStationLocator;
use App\Identity\Entity\Device;
use App\Identity\Entity\Operator;
use App\Identity\Repository\OperatorRepository;
use App\Incident\Entity\Incident;
use App\Incident\Enum\IncidentEventType;
use App\Incident\Enum\IncidentResolution;
use App\Incident\Enum\IncidentStatus;
use App\Incident\Service\AreaCalculator;
use App\Incident\Service\IncidentTimeline;
use App\Intelligence\Entity\ExternalSource;
use App\Reporting\Entity\Report;
use App\Reporting\Entity\ReportPhoto;
use App\Reporting\Enum\ReportType;
use App\Reporting\Model\PhotoAnalysis;
use App\Reporting\Service\PhotoStorage;
use App\Shared\Geo\H3;
use App\Shared\Geo\Point;
use App\Shared\Geo\Region;
use App\Shared\Poi\PoiRef;
use App\Shelter\Entity\Shelter;
use App\Shelter\Enum\ShelterAvailability;
use App\Shelter\Enum\ShelterOccupancy;
use App\Shelter\Enum\ShelterStatus;
use App\Shelter\Repository\ShelterRepository;
use App\Shelter\Service\ShelterLocator;
use App\Verification\Entity\VerificationRequest;
use App\Verification\Entity\VerificationWave;
use App\Verification\Enum\VerificationAnswer;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Builds a believable, varied data set for the Command Center: every report type in every state, area incidents
 * with hexagon footprints and point incidents pinned to stations / shelters, waves, answers, sources, alerts,
 * photos and history. Deterministic for a given seed. Everything it creates is marked simulated / fixture.
 */
final class FixtureBuilder
{
    public const string SOURCE = 'fixture';

    private FixtureRandom $rng;
    private FixtureClock $clock;

    /** @var array<string, int> */
    private array $counts = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $db,
        private readonly H3 $h3,
        private readonly AreaCalculator $areaCalculator,
        private readonly IncidentTimeline $timeline,
        private readonly IncidentConfidenceUpdater $confidence,
        private readonly PhotoStorage $photos,
        private readonly FuelStationRepository $stations,
        private readonly ShelterRepository $shelters,
        private readonly OperatorRepository $operators,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ZoneCatalog $catalog,
        private readonly Region $region,
    ) {
        $this->rng = new FixtureRandom(42);
        $this->clock = new FixtureClock();
    }

    /** @return array<string, int> what was created */
    public function load(int $seed, int $zoneLimit, bool $reset): array
    {
        $this->rng = new FixtureRandom($seed);
        $this->clock = new FixtureClock();
        $this->counts = [];

        if ($reset) {
            $this->reset();
        }
        $this->ensureOperators();

        foreach ($this->catalog->zones($zoneLimit) as $index => $zone) {
            $stations = $this->ensureFuelStations($zone);
            $shelters = $this->ensureShelters($zone);
            $devices = $this->devices($zone, 50);
            $this->em->flush();

            foreach ($this->areaPlan($index) as [$type, $state]) {
                $this->areaIncident($zone, $type, $state, $devices);
            }
            foreach ($this->pointPlan($index) as [$type, $state]) {
                $this->pointIncident($zone, $type, $state, $devices, $stations, $shelters);
            }
            $this->randomPoiConfirmations($stations, $shelters);

            $this->em->flush();
            $this->em->clear();
        }

        ksort($this->counts);

        return $this->counts;
    }

    // ------------------------------------------------------------------ setup

    private function reset(): void
    {
        $this->db->executeStatement('TRUNCATE incident_event, report_photo, verification_request, verification_wave, external_source, alert, report, incident, shelter_status_report, simulation_scenario CASCADE');
        $this->db->executeStatement('DELETE FROM device WHERE simulated = true');
        $this->db->executeStatement('DELETE FROM fuel_station WHERE source = :s', ['s' => self::SOURCE]);
        $this->db->executeStatement('DELETE FROM shelter WHERE source = :s', ['s' => self::SOURCE]);
        $this->bump('reset.photo_files_deleted', $this->photos->purgeAll());
        $this->bump('reset', 1);
    }

    private function ensureOperators(): void
    {
        if ([] !== $this->operators->findAll()) {
            return;
        }
        foreach ([
            ['operator@tarcza.local', 'Operator dyżurny', ['ROLE_OPERATOR']],
            ['analyst@tarcza.local', 'Analityk', ['ROLE_ANALYST']],
            ['admin@tarcza.local', 'Administrator', ['ROLE_ADMIN']],
        ] as [$email, $name, $roles]) {
            $operator = new Operator($email, $name, $roles);
            $operator->setPassword($this->hasher->hashPassword($operator, 'tarcza-demo'));
            $operator->setOrganisation('Centrum Zarządzania Kryzysowego - demo');
            $this->em->persist($operator);
            $this->bump('operators');
        }
        $this->em->flush();
    }

    /** @return list<FuelStation> */
    private function ensureFuelStations(Zone $zone): array
    {
        $existing = $this->stations->findWithinRadius($zone->center, 3000, 40);
        if (\count($existing) >= 5) {
            return $existing;
        }
        $brands = ['Orlen', 'BP', 'Shell', 'Circle K', 'MOL', 'Moya', 'Amic'];
        for ($i = \count($existing); $i < 6; ++$i) {
            $location = $this->rng->around($zone->center, 1500);
            $brand = $this->rng->pick($brands);
            $street = $this->rng->pick($zone->streets);
            $station = new FuelStation(\sprintf('%s %s', $brand, $street), $location, $this->h3->cellFor($location), self::SOURCE, \sprintf('%s-%s-%d', $zone->name, $brand, $i));
            $station->setBrand($brand);
            $station->setAddress(\sprintf('ul. %s %d, %s', $street, $this->rng->int(2, 180), $this->region->name));
            $station->setFuelTypes($this->rng->chance(0.7) ? FuelType::cases() : [FuelType::Pb95, FuelType::Diesel]);
            $this->em->persist($station);
            $existing[] = $station;
            $this->bump('fuel_stations');
        }

        return $existing;
    }

    /** @return list<Shelter> */
    private function ensureShelters(Zone $zone): array
    {
        $existing = $this->shelters->findWithinRadius($zone->center, 3000, 40);
        if (\count($existing) >= 5) {
            return $existing;
        }
        for ($i = \count($existing); $i < 6; ++$i) {
            $location = $this->rng->around($zone->center, 1500);
            $street = $this->rng->pick($zone->streets);
            $address = \sprintf('ul. %s %d, %s', $street, $this->rng->int(1, 120), $this->region->name);
            $shelter = new Shelter('Miejsce ochronne · '.$address, $location, self::SOURCE);
            $shelter->setAddress($address);
            $shelter->setCapacity($this->rng->pick([80, 150, 300, 500, 800, 1200]));
            $shelter->setAvailability($this->rng->pick([ShelterAvailability::Always, ShelterAvailability::OnDemand, ShelterAvailability::Scheduled]));
            $shelter->setRegion([$this->region->name, $zone->name]);
            $this->em->persist($shelter);
            $existing[] = $shelter;
            $this->bump('shelters');
        }

        return $existing;
    }

    /** @return list<Device> */
    private function devices(Zone $zone, int $n): array
    {
        $devices = [];
        for ($i = 0; $i < $n; ++$i) {
            $device = new Device();
            $device->setPlatform($this->rng->pick(['android', 'ios']));
            $device->setAppVersion('0.3.'.$this->rng->int(0, 9));
            $device->markSimulated();
            $location = $this->rng->around($zone->center, 2500);
            $device->updateLocation($location, $this->h3->cellFor($location));
            $this->clock->backdate($device, [
                'createdAt' => $this->clock->ago(\sprintf('%d days', $this->rng->int(1, 40))),
                'lastSeenAt' => $this->clock->ago(\sprintf('%d minutes', $this->rng->int(1, 20 * 60))),
            ]);
            $this->em->persist($device);
            $devices[] = $device;
            $this->bump('devices');
        }

        return $devices;
    }

    // ------------------------------------------------------------------ plans

    /** @return list<array{0: ReportType, 1: string}> */
    private function areaPlan(int $zoneIndex): array
    {
        $states = ['detected', 'verifying', 'active', 'confirmed', 'resolved_confirmed', 'resolved_false_alarm', 'resolved_expired'];
        $plan = [];
        $types = [ReportType::PowerOutage, ReportType::WaterOutage, ReportType::RoadBlocked, ReportType::OtherThreat];
        foreach ($types as $t => $type) {
            $plan[] = [$type, $states[($zoneIndex + $t) % \count($states)]];
            $plan[] = [$type, $states[($zoneIndex + $t + 3) % \count($states)]];
        }

        return $plan;
    }

    /** @return list<array{0: ReportType, 1: string}> */
    private function pointPlan(int $zoneIndex): array
    {
        $fuelStates = ['verifying', 'confirmed', 'detected', 'resolved_confirmed'];
        $shelterStates = ['active', 'verifying', 'resolved_false_alarm'];

        return [
            [ReportType::FuelShortage, $fuelStates[$zoneIndex % 4]],
            [ReportType::FuelShortage, $fuelStates[($zoneIndex + 1) % 4]],
            [ReportType::ShelterIssue, $shelterStates[$zoneIndex % 3]],
        ];
    }

    // ------------------------------------------------------------------ area incidents

    /** @param list<Device> $devices */
    private function areaIncident(Zone $zone, ReportType $type, string $state, array $devices): void
    {
        $t0 = $this->clock->ago(\sprintf('%d minutes', $this->rng->int(20, 36 * 60)));
        $center = $this->rng->around($zone->center, 1500);
        $centerCell = $this->h3->cellFor($center);
        $incident = new Incident($type, $center, $centerCell);
        $this->em->persist($incident);
        $this->clock->backdate($incident, ['startedAt' => $t0, 'lastActivityAt' => $t0]);
        $district = $zone->name;

        $reportCount = match ($state) {
            'detected' => $this->rng->int(1, 2),
            'verifying' => $this->rng->int(3, 5),
            'confirmed', 'resolved_confirmed' => $this->rng->int(9, 14),
            default => $this->rng->int(5, 10),
        };
        $reports = $this->reports($incident, $devices, $center, 700, $reportCount, $t0, $this->areaDescriptions($type, $district));
        $this->event($incident, IncidentEventType::Created, $t0, ['reports' => 1, 'cell' => $centerCell, 'ring' => 0]);
        foreach (\array_slice($reports, 1) as $i => $report) {
            $this->event($incident, IncidentEventType::ReportAttached, $report->getCreatedAt(), ['reports' => $i + 2, 'cell' => $report->getH3Cell(), 'ring' => $this->h3->distance($centerCell, $report->getH3Cell())]);
        }

        $rings = match ($state) {
            'detected' => 0,
            'verifying' => 2,
            default => 3,
        };
        $t = $t0->modify('+90 seconds');
        for ($ring = 0; $ring < $rings; ++$ring) {
            $openWave = 'verifying' === $state && $ring === $rings - 1;
            $cells = 0 === $ring ? $this->h3->gridDisk($centerCell, 1) : $this->h3->gridRing($centerCell, $ring + 1);
            $t = $this->areaWave($incident, $ring, $cells, $devices, $t, $openWave, \in_array($state, ['confirmed', 'resolved_confirmed'], true));
        }
        if ($rings > 0) {
            $incident->setCurrentRing($rings - 1);
        }
        $this->areaCalculator->recompute($incident);

        $this->finishIncident($incident, $state, $type, $district, $reports, $t, $zone);
    }

    /**
     * @param list<string> $cells
     * @param list<Device> $devices
     */
    private function areaWave(Incident $incident, int $ring, array $cells, array $devices, DateTimeImmutable $start, bool $leaveOpen, bool $tightBoundary): DateTimeImmutable
    {
        $wave = new VerificationWave($incident, $ring, $cells, 90);
        $this->em->persist($wave);
        $end = $start->modify('+90 seconds');
        $this->clock->backdate($wave, ['startedAt' => $start, 'expiresAt' => $leaveOpen ? $this->clock->now()->modify('+60 seconds') : $end, 'closedAt' => $leaveOpen ? null : $end]);
        $sent = 0;
        $centerCell = $incident->getCenterCell();

        foreach ($cells as $cell) {
            $distance = $this->h3->distance($centerCell, $cell);
            $incidentCell = $incident->cell($cell, $distance);
            // Inner rings mostly confirm the problem and hold most of the people asked; outer rings mostly deny it.
            $pProblem = match (true) {
                0 === $distance => 0.95,
                1 === $distance => 0.85,
                2 === $distance => 0.45,
                default => 0.15,
            };
            // Confirmed incidents have a sharp boundary: almost nobody beyond ring 2 was asked (or needed).
            $asked = $tightBoundary
                ? ($distance >= 2 ? ($this->rng->chance(0.2) ? 1 : 0) : $this->rng->int(2, 4))
                : $this->rng->int(0, max(0, 4 - $distance));
            if ($tightBoundary && $distance <= 1) {
                $pProblem = 0.97;
            }
            $incidentCell->addAsked($asked);
            for ($i = 0; $i < $asked; ++$i) {
                $device = $this->rng->pick($devices);
                $device->updateLocation($this->h3->center($cell), $cell);
                $request = new VerificationRequest($wave, $device, $cell, $incident->getType()->verificationQuestion());
                $this->em->persist($request);
                ++$sent;
                $sentAt = $start->modify(\sprintf('+%d seconds', $this->rng->int(0, 5)));
                $this->clock->backdate($request, ['sentAt' => $sentAt, 'expiresAt' => $wave->getExpiresAt()]);
                if ($leaveOpen && $this->rng->chance(0.5)) {
                    continue; // still unanswered
                }
                $problem = $this->rng->chance($pProblem);
                $answer = $this->rng->chance(0.1) ? VerificationAnswer::Unknown : ($problem === $incident->getType()->yesMeansProblemPresent() ? VerificationAnswer::Yes : VerificationAnswer::No);
                $normalised = $request->answerWith($answer);
                $this->clock->backdate($request, ['answeredAt' => $sentAt->modify(\sprintf('+%d seconds', $this->rng->int(5, 80)))]);
                $incidentCell->addAnswer($normalised);
            }
        }
        $wave->setRequestsSent($sent);
        $this->event($incident, IncidentEventType::WaveStarted, $start, ['ring' => $ring, 'cells' => \count($cells), 'devices' => $sent]);
        $this->bump('waves');

        if ($leaveOpen) {
            return $end;
        }
        $this->event($incident, IncidentEventType::WaveClosed, $end, ['ring' => $ring]);
        $tallies = $incident->tallies();
        $this->event($incident, IncidentEventType::AreaChanged, $end->modify('+1 second'), [
            'positiveCells' => $tallies['positive'], 'negativeCells' => $tallies['negative'],
            'unknownCells' => $tallies['cells'] - $tallies['positive'] - $tallies['negative'], 'yes' => $tallies['yes'], 'no' => $tallies['no'],
        ]);

        return $end->modify('+20 seconds');
    }

    // ------------------------------------------------------------------ point incidents

    /**
     * @param list<Device>      $devices
     * @param list<FuelStation> $stations
     * @param list<Shelter>     $shelters
     */
    private function pointIncident(Zone $zone, ReportType $type, string $state, array $devices, array $stations, array $shelters): void
    {
        $t0 = $this->clock->ago(\sprintf('%d minutes', $this->rng->int(15, 30 * 60)));
        $fuelTypes = [];
        if (ReportType::FuelShortage === $type) {
            if ([] === $stations) {
                return;
            }
            $station = $this->rng->pick($stations);
            $poi = FuelStationLocator::ref($station);
            $fuelTypes = $this->rng->some([] !== $station->getFuelTypes() ? $station->getFuelTypes() : FuelType::cases(), 1, 2);
            $neighbours = array_values(array_filter($stations, static fn (FuelStation $s) => !$s->getId()->equals($station->getId())));
            $neighbourRefs = array_map(FuelStationLocator::ref(...), \array_slice($neighbours, 0, 3));
        } else {
            if ([] === $shelters) {
                return;
            }
            $shelter = $this->rng->pick($shelters);
            $poi = ShelterLocator::ref($shelter);
            $neighbours = array_values(array_filter($shelters, static fn (Shelter $s) => !$s->getId()->equals($shelter->getId())));
            $neighbourRefs = array_map(ShelterLocator::ref(...), \array_slice($neighbours, 0, 3));
        }

        $cell = $this->h3->cellFor($poi->location);
        $incident = new Incident($type, $poi->location, $cell);
        $incident->bindToPoi($poi, $cell);
        $incident->mergeFuelTypes($fuelTypes);
        $this->em->persist($incident);
        $this->clock->backdate($incident, ['startedAt' => $t0, 'lastActivityAt' => $t0]);

        $descriptions = ReportType::FuelShortage === $type
            ? ['Brak '.FuelType::labels($fuelTypes).', dystrybutory zaplombowane', 'Kolejka na pół kilometra, obsługa mówi że dostawa jutro', null, 'Tylko LPG działa']
            : ['Drzwi zamknięte na kłódkę, nikt nie otwiera', 'Pełno ludzi, nie wpuszczają', null, 'Brak światła w środku, schody zalane'];
        $reports = $this->reports($incident, $devices, $poi->location, 120, 'detected' === $state ? 1 : $this->rng->int(2, 5), $t0, $descriptions, $poi, $fuelTypes);
        $this->event($incident, IncidentEventType::Created, $t0, ['reports' => 1, 'poiKind' => $poi->kind->value, 'poiId' => $poi->id->toRfc4122(), 'poiName' => $poi->name, 'fuelTypes' => implode(',', array_map(static fn (FuelType $f) => $f->value, $fuelTypes))]);
        foreach (\array_slice($reports, 1) as $i => $report) {
            $this->event($incident, IncidentEventType::ReportAttached, $report->getCreatedAt(), ['reports' => $i + 2, 'poiName' => $poi->name]);
        }

        $t = $t0->modify('+60 seconds');
        if ('detected' !== $state) {
            $t = $this->pointWave($incident, 0, [$poi], $devices, $t, true, false);
            $t = $this->pointWave($incident, 1, $neighbourRefs, $devices, $t, false, 'verifying' === $state);
            $incident->setCurrentRing(1);
        }

        // Mirror the crowd verdict on the objects themselves.
        if (ReportType::FuelShortage === $type && isset($station)) {
            $station->confirmAvailability($fuelTypes, 'resolved_false_alarm' === $state ? FuelAvailability::Available : FuelAvailability::Unavailable);
            foreach (\array_slice($neighbours, 0, 3) as $n) {
                $n->confirmAvailability($fuelTypes, $this->rng->chance(0.75) ? FuelAvailability::Available : FuelAvailability::Unavailable, false);
            }
        } elseif (isset($shelter)) {
            if ('resolved_false_alarm' === $state) {
                $shelter->confirmStatus(ShelterStatus::Open, ShelterOccupancy::Plenty);
            } else {
                $shelter->confirmStatus($this->rng->chance(0.5) ? ShelterStatus::Closed : ShelterStatus::Open, $this->rng->chance(0.5) ? ShelterOccupancy::Full : ShelterOccupancy::Limited);
            }
        }
        $this->areaCalculator->recompute($incident);

        $this->finishIncident($incident, $state, $type, $poi->name, $reports, $t, $zone);
    }

    /**
     * @param list<PoiRef> $targets
     * @param list<Device> $devices
     */
    private function pointWave(Incident $incident, int $ring, array $targets, array $devices, DateTimeImmutable $start, bool $aboutIncidentPoi, bool $leaveOpen): DateTimeImmutable
    {
        if ([] === $targets) {
            return $start;
        }
        $wave = new VerificationWave($incident, $ring, array_map(static fn (PoiRef $p) => $p->id->toRfc4122(), $targets), 90);
        $this->em->persist($wave);
        $end = $start->modify('+90 seconds');
        $this->clock->backdate($wave, ['startedAt' => $start, 'expiresAt' => $leaveOpen ? $this->clock->now()->modify('+60 seconds') : $end, 'closedAt' => $leaveOpen ? null : $end]);
        $detail = FuelType::labels($incident->getFuelTypes());
        $sent = 0;
        foreach ($targets as $poi) {
            $asked = $this->rng->int(1, 3);
            for ($i = 0; $i < $asked; ++$i) {
                $device = $this->rng->pick($devices);
                $location = $this->rng->around($poi->location, 80);
                $device->updateLocation($location, $this->h3->cellFor($location));
                $request = new VerificationRequest($wave, $device, (string) $device->getH3Cell(), $incident->getType()->pointVerificationQuestion($poi->name, $detail), $poi);
                $this->em->persist($request);
                ++$sent;
                $sentAt = $start->modify(\sprintf('+%d seconds', $this->rng->int(0, 5)));
                $this->clock->backdate($request, ['sentAt' => $sentAt, 'expiresAt' => $wave->getExpiresAt()]);
                if ($leaveOpen && $this->rng->chance(0.5)) {
                    continue;
                }
                // At the reported object the problem is mostly confirmed; at neighbours mostly denied.
                $problem = $this->rng->chance($aboutIncidentPoi ? 0.85 : 0.25);
                $answer = $problem === $incident->getType()->yesMeansProblemPresent() ? VerificationAnswer::Yes : VerificationAnswer::No;
                $normalised = $request->answerWith($answer);
                $this->clock->backdate($request, ['answeredAt' => $sentAt->modify(\sprintf('+%d seconds', $this->rng->int(5, 80)))]);
                if ($aboutIncidentPoi) {
                    $incident->cell($incident->getCenterCell(), 0)->addAnswer($normalised);
                }
            }
        }
        $incident->cell($incident->getCenterCell(), 0)->addAsked($sent);
        $wave->setRequestsSent($sent);
        $this->event($incident, IncidentEventType::WaveStarted, $start, ['ring' => $ring, 'pois' => \count($targets), 'devices' => $sent, 'poiNames' => implode(' | ', array_map(static fn (PoiRef $p) => $p->name, $targets))]);
        $this->bump('waves');
        if ($leaveOpen) {
            return $end;
        }
        $this->event($incident, IncidentEventType::WaveClosed, $end, ['ring' => $ring]);

        return $end->modify('+20 seconds');
    }

    // ------------------------------------------------------------------ shared

    /**
     * @param list<Device>      $devices
     * @param list<string|null> $descriptions
     * @param list<FuelType>    $fuelTypes
     *
     * @return list<Report>
     */
    private function reports(Incident $incident, array $devices, Point $around, int $radius, int $count, DateTimeImmutable $t0, array $descriptions, ?PoiRef $poi = null, array $fuelTypes = []): array
    {
        $reports = [];
        for ($i = 0; $i < $count; ++$i) {
            $device = $this->rng->pick($devices);
            $location = $this->rng->around($around, $radius);
            $cell = $this->h3->cellFor($location);
            $device->updateLocation($location, $cell);
            $report = new Report($device, $incident->getType(), $location, $cell, $this->rng->pick($descriptions));
            if (null !== $poi) {
                $report->attachPoi($poi);
                $report->setFuelTypes($fuelTypes);
            }
            $this->clock->backdate($report, ['createdAt' => $t0->modify(\sprintf('+%d seconds', 0 === $i ? 0 : $this->rng->int(30, 25 * 60)))]);
            $this->em->persist($report);
            $incident->addReport($report);
            $ring = null === $poi ? $this->h3->distance($incident->getCenterCell(), $cell) : 0;
            $incident->cell(null === $poi ? $cell : $incident->getCenterCell(), $ring)->addReport();
            $reports[] = $report;
            $this->bump('reports');
        }
        usort($reports, static fn (Report $a, Report $b) => $a->getCreatedAt() <=> $b->getCreatedAt());

        return $reports;
    }

    /** @param list<Report> $reports */
    private function finishIncident(Incident $incident, string $state, ReportType $type, string $placeName, array $reports, DateTimeImmutable $t, Zone $zone): void
    {
        $confirmedLike = \in_array($state, ['confirmed', 'resolved_confirmed'], true);
        $hasSources = $confirmedLike || ('active' === $state && $this->rng->chance(0.5));

        if ($hasSources) {
            $t = $this->sources($incident, $type, $placeName, $zone, $t, $confirmedLike);
        }
        if (\in_array($state, ['active', 'confirmed', 'resolved_confirmed', 'resolved_false_alarm'], true) && $this->rng->chance(0.6)) {
            $this->photos($reports, $type, $this->rng->int(1, 2));
        }

        $incident->setStatus(match ($state) {
            'detected' => IncidentStatus::Detected,
            'verifying' => IncidentStatus::Verifying,
            default => IncidentStatus::Active,
        });
        // Open incidents are alive: their last activity is minutes old even when they started yesterday,
        // otherwise freshness decay would flatten every confidence level to "unverified".
        $recent = $this->clock->ago(\sprintf('%d minutes', $confirmedLike ? $this->rng->int(1, 5) : $this->rng->int(1, 40)));
        $this->clock->backdate($incident, ['lastActivityAt' => $recent, 'lastConfirmedAt' => $hasSources ? $recent : null]);
        $this->em->flush();

        $result = $this->confidence->update($incident);
        if ($result['previous'] !== $result['current']) {
            $this->event($incident, IncidentEventType::ConfidenceChanged, $t, ['from' => $result['previous']->value, 'to' => $result['current']->value, 'score' => round($result['score'], 3), 'reason' => 'fixture']);
        }
        if (str_starts_with($state, 'resolved_')) {
            // Closed incidents keep the level they had when they were closed, but their activity stays in the past.
            $this->clock->backdate($incident, ['lastActivityAt' => $t, 'lastConfirmedAt' => $hasSources ? $t : null]);
        }

        if ($confirmedLike && null !== $incident->getArea()) {
            $this->alerts($incident, $type, $placeName, $t->modify('+2 minutes'));
            $t = $t->modify('+2 minutes');
        }

        if (str_starts_with($state, 'resolved_')) {
            $resolution = IncidentResolution::from(substr($state, 9));
            $closedAt = $t->modify(\sprintf('+%d minutes', $this->rng->int(10, 240)));
            $incident->setStatus(IncidentStatus::Resolved, $resolution);
            $this->clock->backdate($incident, ['resolvedAt' => $closedAt, 'lastActivityAt' => $closedAt]);
            $this->event($incident, IncidentEventType::Resolved, $closedAt, ['resolution' => $resolution->value]);
        }
        $this->bump('incidents');
        $this->bump('incidents.'.$type->value);
    }

    private function sources(Incident $incident, ReportType $type, string $placeName, Zone $zone, DateTimeImmutable $t, bool $official): DateTimeImmutable
    {
        $catalog = [
            ReportType::PowerOutage->value => [['Enea Operator', 'https://www.operator.enea.pl/komunikaty/awaria-%s', 'Awaria sieci SN: %s', ExternalSource::KIND_OPERATOR, 0.9], ['TVN24', 'https://tvn24.pl/%s-bez-pradu', 'Kilka tysięcy odbiorców bez prądu w rejonie %s', ExternalSource::KIND_MEDIA, 0.7]],
            ReportType::WaterOutage->value => [['Aquanet', 'https://www.aquanet.pl/awarie/%s', 'Awaria magistrali wodociągowej: %s', ExternalSource::KIND_OPERATOR, 0.9], ['RMF24', 'https://www.rmf24.pl/%s-brak-wody', 'Brak wody w %s, podstawiono beczkowozy', ExternalSource::KIND_MEDIA, 0.7]],
            ReportType::RoadBlocked->value => [['GDDKiA', 'https://www.gov.pl/gddkia/utrudnienia/%s', 'Utrudnienia w ruchu: %s', ExternalSource::KIND_OFFICIAL, 0.85], ['Polsat News', 'https://www.polsatnews.pl/%s-droga', 'Zablokowana droga w %s po wypadku', ExternalSource::KIND_MEDIA, 0.7]],
            ReportType::OtherThreat->value => [['RCB', 'https://www.gov.pl/rcb/alert/%s', 'Alert RCB dla obszaru %s', ExternalSource::KIND_OFFICIAL, 0.9], ['Onet', 'https://wiadomosci.onet.pl/%s-alarm', 'Służby interweniują w %s', ExternalSource::KIND_MEDIA, 0.6]],
            ReportType::FuelShortage->value => [['Orlen', 'https://www.orlen.pl/komunikaty/%s', 'Przerwa w sprzedaży paliw na stacji %s', ExternalSource::KIND_OPERATOR, 0.9], ['Polsat News', 'https://www.polsatnews.pl/%s-paliwo', 'Kolejki i braki paliwa: %s', ExternalSource::KIND_MEDIA, 0.7]],
            ReportType::ShelterIssue->value => [['Urząd Miasta', 'https://www.%s.pl/komunikat/schron', 'Komunikat UM: %s', ExternalSource::KIND_OFFICIAL, 0.85], ['Radio lokalne', 'https://www.radio.%s.pl/schron', 'Mieszkańcy zgłaszają problem ze schronem: %s', ExternalSource::KIND_MEDIA, 0.6]],
        ];
        $rows = $catalog[$type->value] ?? [];
        if (!$official) {
            $rows = \array_slice(array_reverse($rows), 0, 1);
        }
        $slug = mb_strtolower(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $placeName) ?: 'x') ?? 'x');
        foreach ($rows as $i => [$publisher, $urlTpl, $titleTpl, $kind, $credibility]) {
            $foundBy = $this->rng->pick(['ai', 'rss', 'operator']);
            $source = new ExternalSource($incident, \sprintf($urlTpl, $slug.'-'.$incident->getId()->toBase32()), \sprintf($titleTpl, $placeName), $kind, $credibility, $foundBy);
            $source->setPublisher($publisher);
            $source->setExcerpt(\sprintf('%s informuje o zdarzeniu w rejonie %s (%s). Trwają działania służb.', $publisher, $placeName, $this->region->name.', '.$zone->name));
            $source->setPublishedAt($t->modify(\sprintf('-%d minutes', $this->rng->int(2, 40))));
            $this->em->persist($source);
            $this->clock->backdate($source, ['foundAt' => $t->modify(\sprintf('+%d seconds', 30 * $i))]);
            $this->event($incident, IncidentEventType::SourceAdded, $source->getFoundAt(), ['publisher' => $publisher, 'foundBy' => $foundBy]);
            $this->bump('external_sources');
        }
        $incident->recordResearch(\sprintf('%s: potwierdzenie w źródłach publicznych dla %s. %s', $type->label(), $placeName, $official ? 'Komunikat oficjalny opublikowany przed kilkunastoma minutami.' : 'Jedynie doniesienia medialne, brak komunikatu operatora.'));
        $this->clock->backdate($incident, ['researchedAt' => $t->modify('+40 seconds')]);
        $this->event($incident, IncidentEventType::ResearchCompleted, $t->modify('+40 seconds'), ['hasSummary' => true]);

        return $t->modify('+1 minute');
    }

    private function alerts(Incident $incident, ReportType $type, string $placeName, DateTimeImmutable $t): void
    {
        $area = $incident->getArea();
        if (null === $area) {
            return;
        }
        $system = new Alert(\sprintf('Potwierdzono: %s w Twojej okolicy', mb_strtolower($type->label())), 'Problem potwierdza większość odpowiadających użytkowników. Sprawdź procedurę w aplikacji.', Alert::SEVERITY_WARNING, $area, 'system', $t->modify('+3 hours'), $incident);
        $system->setDeliveredCount($this->rng->int(8, 60));
        $this->em->persist($system);
        $this->clock->backdate($system, ['createdAt' => $t]);
        $this->event($incident, IncidentEventType::AlertPublished, $t, ['alertId' => $system->getId()->toRfc4122(), 'severity' => 'warning', 'devices' => $system->getDeliveredCount(), 'createdBy' => 'system']);
        $this->bump('alerts');

        if ($this->rng->chance(0.6)) {
            $op = new Alert(\sprintf('%s: komunikat służb', $placeName), 'Służby pracują nad usunięciem problemu. Najbliższy punkt pomocy znajdziesz w aplikacji.', Alert::SEVERITY_INFO, $area, 'operator@tarcza.local', $t->modify('+6 hours'), $incident);
            $op->setDeliveredCount($this->rng->int(8, 60));
            $this->em->persist($op);
            $this->clock->backdate($op, ['createdAt' => $t->modify('+9 minutes')]);
            $this->event($incident, IncidentEventType::AlertPublished, $t->modify('+9 minutes'), ['alertId' => $op->getId()->toRfc4122(), 'severity' => 'info', 'devices' => $op->getDeliveredCount(), 'createdBy' => 'operator@tarcza.local']);
            $this->bump('alerts');
        }
    }

    /** @param list<Report> $reports */
    private function photos(array $reports, ReportType $type, int $count): void
    {
        foreach ($this->rng->some($reports, $count, $count) as $report) {
            $bytes = $this->jpeg($type->label(), $report->getCreatedAt()->format('d.m H:i'));
            $path = \sprintf('%s/%s/%s.jpg', $report->getCreatedAt()->format('Y/m'), $report->getId()->toRfc4122(), $report->getId()->toBase32());
            $this->photos->write($path, $bytes);
            $photo = new ReportPhoto($report, $path, 'image/jpeg', 960, 720, \strlen($bytes), hash('sha256', $bytes));
            $this->em->persist($photo);
            $this->clock->backdate($photo, ['createdAt' => $report->getCreatedAt()->modify('+40 seconds')]);
            $roll = $this->rng->float();
            if ($roll < 0.6) {
                $photo->recordAnalysis(new PhotoAnalysis(true, true, \sprintf('Zdjęcie pokazuje sytuację zgodną ze zgłoszeniem: %s.', mb_strtolower($type->label())), false, null, $this->rng->float(0.7, 0.98)), 'fixture:vision');
            } elseif ($roll < 0.8) {
                $photo->recordAnalysis(new PhotoAnalysis(true, false, 'Widać problem infrastrukturalny, ale innego rodzaju niż zgłoszony.', false, null, $this->rng->float(0.4, 0.7)), 'fixture:vision');
            } elseif ($roll < 0.9) {
                $photo->recordAnalysis(new PhotoAnalysis(false, false, 'Treść nieodpowiednia do pokazania operatorowi.', true, 'nieodpowiednia treść', 0.9), 'fixture:vision');
            }
            $this->bump('photos');
        }
    }

    private function jpeg(string $label, string $stamp): string
    {
        $img = imagecreatetruecolor(960, 720);
        if (false === $img) {
            return '';
        }
        $base = [$this->rng->int(20, 90), $this->rng->int(20, 90), $this->rng->int(30, 110)];
        for ($y = 0; $y < 720; $y += 8) {
            $shade = (int) (40 * $y / 720);
            $c = imagecolorallocate($img, max(0, min(255, $base[0] + $shade)), max(0, min(255, $base[1] + $shade)), max(0, min(255, $base[2] + $shade)));
            if (false !== $c) {
                imagefilledrectangle($img, 0, $y, 960, $y + 8, $c);
            }
        }
        $white = imagecolorallocate($img, 240, 240, 240);
        if (false !== $white) {
            imagestring($img, 5, 24, 24, strtoupper(iconv('UTF-8', 'ASCII//TRANSLIT', $label) ?: $label), $white);
            imagestring($img, 3, 24, 52, $stamp.' · zdjecie demonstracyjne (fixture)', $white);
        }
        ob_start();
        imagejpeg($img, null, 82);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    /**
     * @param list<FuelStation> $stations
     * @param list<Shelter>     $shelters
     */
    private function randomPoiConfirmations(array $stations, array $shelters): void
    {
        foreach ($stations as $station) {
            if ($this->rng->chance(0.5) && !$station->hasShortage()) {
                $station->confirmAvailability($this->rng->some([] !== $station->getFuelTypes() ? $station->getFuelTypes() : FuelType::cases(), 1, 3), FuelAvailability::Available);
            }
        }
        foreach ($shelters as $shelter) {
            if (ShelterStatus::Unknown === $shelter->getStatus() && $this->rng->chance(0.6)) {
                $shelter->confirmStatus(ShelterStatus::Open, $this->rng->pick([ShelterOccupancy::Plenty, ShelterOccupancy::Limited, ShelterOccupancy::Unknown]));
            }
        }
    }

    /** @return list<string|null> */
    private function areaDescriptions(ReportType $type, string $district): array
    {
        return match ($type) {
            ReportType::PowerOutage => ['Nie ma prądu od kilku minut', 'Cała ulica bez światła', 'Winda stanęła, brak zasilania', null, 'Zgasło wszystko w bloku na '.$district],
            ReportType::WaterOutage => ['Z kranu nie leci', 'Brązowa woda, potem nic', null, 'Hydrofornia nie działa'],
            ReportType::RoadBlocked => ['Drzewo na jezdni', 'Zalane przejście pod wiaduktem', null, 'Wypadek, obie strony stoją', 'Zerwana linia nad drogą'],
            ReportType::OtherThreat => ['Syrena alarmowa od 5 minut', 'Dym nad '.$district, null, 'Silny zapach gazu na klatce', 'Policja zamyka ulicę'],
            default => [null],
        };
    }

    /** @param array<string, scalar|null> $payload */
    private function event(Incident $incident, IncidentEventType $type, DateTimeImmutable $at, array $payload = []): void
    {
        $event = $this->timeline->record($incident, $type, $payload);
        $this->clock->backdate($event, ['createdAt' => $at]);
        $this->bump('timeline_events');
    }

    private function bump(string $key, int $by = 1): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + $by;
    }
}
