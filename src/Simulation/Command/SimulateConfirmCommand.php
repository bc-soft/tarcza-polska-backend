<?php

declare(strict_types=1);

namespace App\Simulation\Command;

use App\Incident\Repository\IncidentRepository;
use App\Intelligence\Entity\ExternalSource;
use App\Intelligence\Service\ExternalSourceRecorder;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * Offline stand-in for AI research: attach a fake "operator statement" to the newest (or given) open incident.
 * Use it on stage when the venue has no internet or no API key.
 */
#[AsCommand(name: 'tarcza:simulate:confirm', description: 'Attach a simulated official confirmation to an incident (pushes confidence towards CONFIRMED)')]
final class SimulateConfirmCommand extends Command
{
    public function __construct(
        private readonly IncidentRepository $incidents,
        private readonly ExternalSourceRecorder $recorder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('incident', InputArgument::OPTIONAL, 'Incident UUID (default: most recent open incident)')
            ->addOption('credibility', null, InputOption::VALUE_REQUIRED, '0..1', '0.9');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string|null $id */
        $id = $input->getArgument('incident');

        $incident = null !== $id ? $this->incidents->find(Uuid::fromString($id)) : ($this->incidents->findOpen()[0] ?? null);
        if (null === $incident) {
            $io->error('No open incident found.');

            return Command::FAILURE;
        }

        $source = $this->recorder->record(
            $incident,
            'https://www.operator.example/komunikaty/awaria-'.$incident->getId()->toBase32(),
            \sprintf('Komunikat operatora: %s - prace awaryjne w toku', $incident->getType()->label()),
            ExternalSource::KIND_OPERATOR,
            (float) $input->getOption('credibility'),
            'simulation',
            'Operator sieci (symulacja)',
            'Operator potwierdza zakłócenie na wskazanym obszarze. Szacowany czas usunięcia awarii: 2 godziny.',
            new DateTimeImmutable('-4 minutes'),
        );

        $io->success(\sprintf('Source %s attached to incident %s. Confidence will be recalculated by the worker.', $source->getId()->toRfc4122(), $incident->getId()->toRfc4122()));

        return Command::SUCCESS;
    }
}
