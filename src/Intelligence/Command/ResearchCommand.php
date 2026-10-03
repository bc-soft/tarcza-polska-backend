<?php

declare(strict_types=1);

namespace App\Intelligence\Command;

use App\Incident\Repository\IncidentRepository;
use App\Intelligence\Message\ResearchIncident;
use App\Intelligence\MessageHandler\ResearchIncidentHandler;
use App\Intelligence\Model\ResearchFinding;
use App\Intelligence\Repository\ExternalSourceRepository;
use App\Intelligence\Service\PlaceNamer;
use App\Intelligence\Service\ResearcherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * Run AI research for an incident right now, synchronously, and print what came back.
 * Useful to test a provider/API key without waiting for the confidence threshold or the worker.
 */
#[AsCommand(name: 'tarcza:research', description: 'Run AI research for an incident now (synchronously) and print the findings')]
final class ResearchCommand extends Command
{
    public function __construct(
        private readonly IncidentRepository $incidents,
        private readonly ExternalSourceRepository $sources,
        private readonly ResearcherInterface $researcher,
        private readonly ResearchIncidentHandler $handler,
        private readonly PlaceNamer $placeNamer,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('incident', InputArgument::OPTIONAL, 'Incident UUID (default: most recent open incident)')
            ->addOption('again', null, InputOption::VALUE_NONE, 'Research even if the incident was already researched')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Call the provider and print findings, but store nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string|null $id */
        $id = $input->getArgument('incident');

        $incident = null !== $id ? $this->incidents->find(Uuid::fromString($id)) : ($this->incidents->findOpen()[0] ?? null);
        if (null === $incident) {
            $io->error('No incident found.');

            return Command::FAILURE;
        }

        $place = $this->placeNamer->nameFor($incident->getCentroid());
        $io->title(\sprintf('Research: %s (%s)', $incident->getType()->label(), $incident->getId()->toRfc4122()));
        $io->text([
            'provider: '.$this->researcher->name().($this->researcher->isEnabled() ? '' : '  (NO API KEY)'),
            'place:    '.$place,
            'before:   '.$incident->getConfidenceLevel()->value.' '.round($incident->getConfidenceScore(), 3).', researched: '.($incident->getResearchedAt()?->format('H:i:s') ?? 'never'),
        ]);

        if (!$this->researcher->isEnabled()) {
            $io->warning('Selected provider has no API key. Set OPENAI_API_KEY / ANTHROPIC_API_KEY in .env.local.');

            return Command::FAILURE;
        }

        $start = microtime(true);

        if ($input->getOption('dry-run')) {
            $result = $this->researcher->research($incident, $place);
            $io->section(\sprintf('Summary (%.1fs, nothing stored)', microtime(true) - $start));
            $io->text('' !== $result->summary ? $result->summary : '(empty)');
            $this->printFindings($io, array_map(
                static fn (ResearchFinding $f) => [$f->confirmsIncident ? 'yes' : 'no', $f->kind, number_format($f->credibility, 2), $f->publisher, $f->title, $f->url],
                $result->findings,
            ));
            if (null !== $result->suggestedVerificationQuestion) {
                $io->text('suggested question: '.$result->suggestedVerificationQuestion);
            }

            return Command::SUCCESS;
        }

        if ($input->getOption('again') && null !== $incident->getResearchedAt()) {
            $incident->recordResearchReset();
            $this->em->flush();
        }
        if (null !== $incident->getResearchedAt()) {
            $io->warning('Already researched. Use --again to run it once more, or --dry-run to only look at the answer.');

            return Command::FAILURE;
        }

        ($this->handler)(new ResearchIncident($incident->getId()->toRfc4122()));
        $this->em->refresh($incident);

        $io->section(\sprintf('Summary (%.1fs)', microtime(true) - $start));
        $io->text($incident->getAiSummary() ?? '(empty)');
        $this->printFindings($io, array_map(
            static fn ($s) => ['yes', $s->getKind(), number_format($s->getCredibility(), 2), (string) $s->getPublisher(), $s->getTitle(), $s->getUrl()],
            $this->sources->findByIncident($incident),
        ));
        $io->success('Stored. Confidence will be recalculated by the worker (IncidentUpdated dispatched).');

        return Command::SUCCESS;
    }

    /** @param list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}> $rows */
    private function printFindings(SymfonyStyle $io, array $rows): void
    {
        if ([] === $rows) {
            $io->text('findings: none');

            return;
        }
        $io->table(
            ['confirms', 'kind', 'cred.', 'publisher', 'title', 'url'],
            array_map(static fn (array $r) => [$r[0], $r[1], $r[2], mb_substr($r[3], 0, 24), mb_substr($r[4], 0, 48), mb_substr($r[5], 0, 60)], $rows),
        );
    }
}
