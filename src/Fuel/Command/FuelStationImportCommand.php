<?php

declare(strict_types=1);

namespace App\Fuel\Command;

use App\Fuel\Entity\FuelStation;
use App\Fuel\Model\FuelStationCandidate;
use App\Fuel\Repository\FuelStationRepository;
use App\Fuel\Service\OverpassFuelStationSource;
use App\Shared\Geo\BoundingBox;
use App\Shared\Geo\H3;
use App\Shared\Geo\Point;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Imports / refreshes fuel stations from OpenStreetMap. Upserts by OSM id; availability data is never touched.
 *
 *   bin/console tarcza:fuel-stations:import --around=52.4121,16.9012 --radius-km=15
 *   bin/console tarcza:fuel-stations:import --bbox=16.70,52.30,17.15,52.55
 */
#[AsCommand(name: 'tarcza:fuel-stations:import', description: 'Import fuel stations from OpenStreetMap (Overpass) around a point or inside a bbox')]
final class FuelStationImportCommand extends Command
{
    public function __construct(
        private readonly OverpassFuelStationSource $source,
        private readonly FuelStationRepository $stations,
        private readonly EntityManagerInterface $em,
        private readonly H3 $h3,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('around', null, InputOption::VALUE_REQUIRED, 'Center "lat,lng"')
            ->addOption('radius-km', null, InputOption::VALUE_REQUIRED, 'Radius around the center in km', '15')
            ->addOption('bbox', null, InputOption::VALUE_REQUIRED, 'minLng,minLat,maxLng,maxLat')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Fetch and report, store nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string|null $around */
        $around = $input->getOption('around');
        /** @var string|null $bbox */
        $bbox = $input->getOption('bbox');

        if (null !== $bbox) {
            $candidates = $this->source->fetchInBoundingBox(BoundingBox::fromString($bbox));
        } elseif (null !== $around) {
            [$lat, $lng] = array_map('floatval', explode(',', $around) + [0 => '0', 1 => '0']);
            $candidates = $this->source->fetchAround(new Point($lat, $lng), (int) round((float) $input->getOption('radius-km') * 1000));
        } else {
            $io->error('Pass --around=lat,lng (with --radius-km) or --bbox=minLng,minLat,maxLng,maxLat');

            return Command::INVALID;
        }

        $io->text(\sprintf('Overpass returned %d stations', \count($candidates)));
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
