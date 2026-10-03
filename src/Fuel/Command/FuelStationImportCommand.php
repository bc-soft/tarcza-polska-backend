<?php

declare(strict_types=1);

namespace App\Fuel\Command;

use App\Fuel\Entity\FuelStation;
use App\Fuel\Exception\OverpassUnavailableException;
use App\Fuel\Model\FuelStationCandidate;
use App\Fuel\Repository\FuelStationRepository;
use App\Fuel\Service\OverpassFuelStationSource;
use App\Shared\Geo\BoundingBox;
use App\Shared\Geo\H3;
use App\Shared\Geo\Point;
use App\Shared\Geo\Region;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Imports / refreshes fuel stations from OpenStreetMap. Upserts by OSM id; availability data is never touched.
 * A region import falls back to the committed Overpass snapshot (app.fuel.osm_snapshot) when no mirror answers.
 *
 *   bin/console tarcza:fuel-stations:import                          # the configured region (Poznań, app.region.*)
 *   bin/console tarcza:fuel-stations:import --from-file=data/osm/fuel-stations-poznan.json   # offline, saved Overpass JSON
 *   bin/console tarcza:fuel-stations:import --around=52.4121,16.9012 --radius-km=5
 *   bin/console tarcza:fuel-stations:import --bbox=16.70,52.30,17.15,52.55
 */
#[AsCommand(name: 'tarcza:fuel-stations:import', description: 'Import fuel stations from OpenStreetMap (Overpass) for the configured region, around a point or inside a bbox')]
final class FuelStationImportCommand extends Command
{
    public function __construct(
        private readonly OverpassFuelStationSource $source,
        private readonly FuelStationRepository $stations,
        private readonly EntityManagerInterface $em,
        private readonly H3 $h3,
        private readonly Region $region,
        private readonly string $fuelOsmSnapshot,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('around', null, InputOption::VALUE_REQUIRED, 'Center "lat,lng" (default: the configured region centre)')
            ->addOption('radius-km', null, InputOption::VALUE_REQUIRED, 'Radius around the center in km (default: the region radius)')
            ->addOption('bbox', null, InputOption::VALUE_REQUIRED, 'minLng,minLat,maxLng,maxLat')
            ->addOption('from-file', null, InputOption::VALUE_REQUIRED, 'Read a saved Overpass JSON answer instead of querying Overpass')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Fetch and report, store nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string|null $around */
        $around = $input->getOption('around');
        /** @var string|null $bbox */
        $bbox = $input->getOption('bbox');

        /** @var string|null $radiusKm */
        $radiusKm = $input->getOption('radius-km');
        /** @var string|null $fromFile */
        $fromFile = $input->getOption('from-file');

        if (null !== $fromFile) {
            $candidates = $this->readSnapshot($io, $fromFile);
            if (null === $candidates) {
                return Command::FAILURE;
            }

            return $this->store($io, $input, $candidates);
        }

        $io->text(\sprintf('Overpass endpoints (queried in parallel): %s', implode(', ', $this->source->endpoints())));
        try {
            if (null !== $bbox) {
                $io->text('Area: bbox '.$bbox);
                $candidates = $this->source->fetchInBoundingBox(BoundingBox::fromString($bbox));
            } else {
                $center = $this->region->center;
                if (null !== $around) {
                    [$lat, $lng] = array_map('floatval', explode(',', $around) + [0 => '0', 1 => '0']);
                    $center = new Point($lat, $lng);
                }
                $radiusMeters = null === $radiusKm ? $this->region->radiusMeters() : (int) round((float) $radiusKm * 1000);
                $io->text(\sprintf('Area: %.4f,%.4f radius %d km%s', $center->lat, $center->lng, intdiv($radiusMeters, 1000), null === $around ? ' (region: '.$this->region->name.')' : ''));
                $candidates = $this->source->fetchAround($center, $radiusMeters);
            }
        } catch (OverpassUnavailableException $e) {
            if (null !== $bbox || null !== $around || null !== $radiusKm) {
                $io->error($e->getMessage());

                return Command::FAILURE;
            }
            $io->warning($e->getMessage()."\nFalling back to the committed snapshot of the region.");
            $candidates = $this->readSnapshot($io, $this->fuelOsmSnapshot);
            if (null === $candidates) {
                return Command::FAILURE;
            }
        }

        return $this->store($io, $input, $candidates);
    }

    /** @return list<FuelStationCandidate>|null null when the file is missing or not an Overpass answer */
    private function readSnapshot(SymfonyStyle $io, string $path): ?array
    {
        $json = is_file($path) ? file_get_contents($path) : false;
        if (false === $json) {
            $io->error('Cannot read '.$path);

            return null;
        }
        try {
            $candidates = OverpassFuelStationSource::parse($json);
        } catch (JsonException|OverpassUnavailableException $e) {
            $io->error(\sprintf('%s is not a usable Overpass answer: %s', $path, $e->getMessage()));

            return null;
        }
        $io->text(\sprintf('Snapshot %s: %d stations', $path, \count($candidates)));

        return $candidates;
    }

    /** @param list<FuelStationCandidate> $candidates */
    private function store(SymfonyStyle $io, InputInterface $input, array $candidates): int
    {
        $io->text(\sprintf('%d stations to import', \count($candidates)));
        if ($input->getOption('dry-run')) {
            foreach (\array_slice($candidates, 0, 20) as $c) {
                $io->text(\sprintf(' - %s | %s | %s | %s', $c->name, $c->brand ?? '-', $c->address ?? '-', implode(',', array_map(static fn ($t) => $t->value, $c->fuelTypes))));
            }

            return Command::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        foreach ($candidates as $i => $c) {
            $existing = $this->stations->findByExternalId(OverpassFuelStationSource::SOURCE, $c->externalId);
            if (null === $existing) {
                $station = new FuelStation($c->name, $c->location, $this->h3->cellFor($c->location), OverpassFuelStationSource::SOURCE, $c->externalId);
                $this->em->persist($station);
                ++$created;
            } else {
                $station = $existing;
                $station->rename($c->name);
                $station->relocate($c->location, $this->h3->cellFor($c->location));
                ++$updated;
            }
            $this->apply($station, $c);
            if (0 === $i % 100) {
                $this->em->flush();
            }
        }
        $this->em->flush();

        $io->success(\sprintf('Fuel stations: %d created, %d updated (source osm).', $created, $updated));

        return Command::SUCCESS;
    }

    private function apply(FuelStation $station, FuelStationCandidate $c): void
    {
        $station->setBrand($c->brand);
        $station->setAddress($c->address);
        if ([] !== $c->fuelTypes) {
            $station->setFuelTypes($c->fuelTypes);
        }
    }
}
