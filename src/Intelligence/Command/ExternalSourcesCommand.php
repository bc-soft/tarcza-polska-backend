<?php

declare(strict_types=1);

namespace App\Intelligence\Command;

use App\Incident\Repository\IncidentRepository;
use App\Intelligence\Message\ExternalSourcesTick;
use App\Intelligence\MessageHandler\ExternalSourcesTickHandler;
use App\Intelligence\Model\FeedItem;
use App\Intelligence\Service\Feed\FeedRegistry;
use App\Intelligence\Service\Feed\IncidentFeedMatcher;
use App\Intelligence\Service\PlaceNamer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Inspect the External Sources Engine by hand: which feeds answer, how many items, what matches open incidents.
 */
#[AsCommand(name: 'tarcza:sources:poll', description: 'Fetch the configured feeds and show (or apply) matches against open incidents')]
final class ExternalSourcesCommand extends Command
{
    public function __construct(
        private readonly FeedRegistry $feeds,
        private readonly IncidentFeedMatcher $matcher,
        private readonly IncidentRepository $incidents,
        private readonly PlaceNamer $placeNamer,
        private readonly ExternalSourcesTickHandler $handler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Store matches as external sources (same as the scheduled tick)')
            ->addOption('show-items', null, InputOption::VALUE_NONE, 'Print the fetched items');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Feeds');
        $items = $this->feeds->fetchAll();
        $perFeed = [];
        foreach ($items as $item) {
            $perFeed[$item->feed->name] = ($perFeed[$item->feed->name] ?? 0) + 1;
        }
        foreach ($this->feeds->feeds() as $feed) {
            $io->text(\sprintf(' %-28s %-12s cred %.2f  items: %d', $feed->name, $feed->kind, $feed->credibility, $perFeed[$feed->name] ?? 0));
        }
        if ($input->getOption('show-items')) {
            $io->table(['feed', 'published', 'title'], array_map(static fn (FeedItem $i) => [$i->feed->name, $i->publishedAt?->format('m-d H:i') ?? '-', mb_substr($i->title, 0, 80)], \array_slice($items, 0, 40)));
        }

        $io->section('Matches against open incidents');
        $any = false;
        foreach ($this->incidents->findOpen() as $incident) {
            $place = $this->placeNamer->nameFor($incident->getCentroid());
            $matches = $this->matcher->match($incident, $place, $items);
            $io->text(\sprintf(' %s · %s · %s · %d match(es)', $incident->getId()->toRfc4122(), $incident->getType()->label(), $place, \count($matches)));
            foreach ($matches as $m) {
                $any = true;
                $io->text(\sprintf('    %.2f  %s  [%s | %s]  %s', $m->score, mb_substr($m->item->title, 0, 70), implode(',', $m->typeHits), implode(',', $m->placeHits), $m->item->url));
            }
        }

        if ($input->getOption('apply')) {
            ($this->handler)(new ExternalSourcesTick());
            $io->success('Applied: matching items were recorded as external sources.');
        } elseif ($any) {
            $io->note('Dry run. Re-run with --apply to store the matches.');
        }

        return Command::SUCCESS;
    }
}
