<?php

declare(strict_types=1);

namespace App\Simulation\Command;

use App\Identity\Entity\Device;
use App\Reporting\Dto\CreateReportRequest;
use App\Reporting\Enum\ReportType;
use App\Reporting\Service\ReportSubmitter;
use App\Shared\Geo\H3;
use App\Shared\Geo\Point;
use App\Simulation\Entity\SimulationScenario;
use App\Simulation\Repository\SimulationScenarioRepository;
use App\Simulation\Service\GeoRandom;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Demo scenario in one command:
 *   1. spawn N simulated devices around the center (inside and outside the future outage),
 *   2. file the first reports from devices inside the outage,
 *   3. leave the rest to the engines: clustering -> waves -> simulated answers -> dynamic area -> research.
 */
#[AsCommand(name: 'tarcza:simulate:outage', description: 'Spawn a simulated crowd and an infrastructure outage (demo scenario)')]
final class SimulateOutageCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SimulationScenarioRepository $scenarios,
        private readonly ReportSubmitter $submitter,
        private readonly H3 $h3,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Report type', ReportType::PowerOutage->value)
            ->addOption('lat', null, InputOption::VALUE_REQUIRED, 'Center latitude', '52.4121')
            ->addOption('lng', null, InputOption::VALUE_REQUIRED, 'Center longitude', '16.9012')
            ->addOption('radius', null, InputOption::VALUE_REQUIRED, 'Outage radius in meters (ground truth)', '1100')
            ->addOption('spread', null, InputOption::VALUE_REQUIRED, 'Radius in meters in which simulated devices live', '3000')
            ->addOption('devices', null, InputOption::VALUE_REQUIRED, 'Number of simulated devices', '700')
            ->addOption('reports', null, InputOption::VALUE_REQUIRED, 'Initial reports from inside the outage', '5')
            ->addOption('accuracy', null, InputOption::VALUE_REQUIRED, 'Share of truthful answers', '0.9')
            ->addOption('keep', null, InputOption::VALUE_NONE, 'Keep previous scenarios active (default: deactivate them)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $type = ReportType::from((string) $input->getOption('type'));
        $center = new Point((float) $input->getOption('lat'), (float) $input->getOption('lng'));
        $radius = (int) $input->getOption('radius');
        $spread = (int) $input->getOption('spread');
        $deviceCount = (int) $input->getOption('devices');
        $reportCount = (int) $input->getOption('reports');
        $accuracy = (float) $input->getOption('accuracy');

        if (!$input->getOption('keep')) {
            $this->scenarios->deactivateAll();
        }

        $scenario = new SimulationScenario(\sprintf('%s @ %.4f,%.4f r=%dm', $type->label(), $center->lat, $center->lng, $radius), $type, $center, $radius, $accuracy);
        $this->em->persist($scenario);

        $io->section('Spawning simulated crowd');
        $inside = [];
        $progress = $io->createProgressBar($deviceCount);
        for ($i = 0; $i < $deviceCount; ++$i) {
            $location = GeoRandom::around($center, $spread);
            $device = new Device();
            $device->setPlatform('simulator');
            $device->markSimulated();
            $device->updateLocation($location, $this->h3->cellFor($location));
            $this->em->persist($device);
            if ($scenario->contains($location)) {
                $inside[] = $device;
            }
            if (0 === $i % 50) {
                $this->em->flush();
            }
            $progress->advance();
        }
        $this->em->flush();
        $progress->finish();
        $io->newLine(2);
        $io->text(\sprintf('%d devices, %d of them inside the outage', $deviceCount, \count($inside)));

        if ([] === $inside) {
            $io->error('No simulated device fell inside the outage radius; increase --devices or --radius.');

            return Command::FAILURE;
        }

        $io->section('Filing initial reports');
        shuffle($inside);
        $descriptions = ['Nie ma prądu od kilku minut', 'Cała ulica bez światła', 'Winda stanęła, brak zasilania', null, 'Zgasło wszystko w bloku', null];
        foreach (\array_slice($inside, 0, min($reportCount, \count($inside))) as $k => $device) {
            $location = $device->getLastLocation() ?? $center;
            $report = $this->submitter->submit($device, new CreateReportRequest($type, $location->lat, $location->lng, $descriptions[$k % \count($descriptions)]));
            $io->text(\sprintf(' - report %s from cell %s', $report->getId()->toRfc4122(), $report->getH3Cell()));
        }

        $io->success([
            'Scenario active: '.$scenario->getName(),
            'Watch the worker (make worker) and the Command Center (https://localhost/command).',
            'Expected flow: cluster -> wave 0 -> simulated answers -> area grows -> frontier rings -> boundary stable.',
            'Add an official confirmation with: bin/console tarcza:simulate:confirm',
        ]);

        return Command::SUCCESS;
    }
}
