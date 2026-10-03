<?php

declare(strict_types=1);

namespace App\Simulation\Command;

use App\Simulation\Fixture\FixtureBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fills the database with a varied, realistic-looking data set so the Command Center has something to show:
 * all report types, every incident state, area footprints and station / shelter pins, waves, sources, alerts,
 * photos and history across several Polish cities.
 *
 *   bin/console tarcza:fixtures:load --reset            # wipe previous fixtures / demo data first
 *   bin/console tarcza:fixtures:load --cities=2 --seed=7
 */
#[AsCommand(name: 'tarcza:fixtures:load', description: 'Load varied demo fixtures (all report types and states) for the Command Center')]
final class FixturesLoadCommand extends Command
{
    public function __construct(private readonly FixtureBuilder $builder)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Truncate incidents, reports, alerts, sources, photos; delete simulated devices and fixture stations / shelters')
            ->addOption('cities', null, InputOption::VALUE_REQUIRED, 'How many cities to populate (1-6)', '4')
            ->addOption('seed', null, InputOption::VALUE_REQUIRED, 'Random seed (same seed = same data)', '42');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cities = max(1, min(6, (int) $input->getOption('cities')));
        $seed = (int) $input->getOption('seed');

        $io->title(\sprintf('Fixtures: %d cities, seed %d%s', $cities, $seed, $input->getOption('reset') ? ', reset first' : ''));
        $started = microtime(true);

        $counts = $this->builder->load($seed, $cities, (bool) $input->getOption('reset'));

        $io->table(['what', 'created'], array_map(static fn (string $k, int $v) => [$k, $v], array_keys($counts), $counts));
        $io->success(\sprintf('Done in %.1fs. Open the Command Center: incidents list, map, shelters, fuel stations.', microtime(true) - $started));

        return Command::SUCCESS;
    }
}
