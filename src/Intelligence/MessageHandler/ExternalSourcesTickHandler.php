<?php

declare(strict_types=1);

namespace App\Intelligence\MessageHandler;

use App\Incident\Entity\Incident;
use App\Incident\Repository\IncidentRepository;
use App\Intelligence\Message\ExternalSourcesTick;
use App\Intelligence\Repository\ExternalSourceRepository;
use App\Intelligence\Service\ExternalSourceRecorder;
use App\Intelligence\Service\Feed\FeedRegistry;
use App\Intelligence\Service\Feed\IncidentFeedMatcher;
use App\Intelligence\Service\PlaceNamer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * External Sources Engine tick: feeds are fetched once (cached), every open incident is matched, new hits
 * become ExternalSource rows (found_by = rss) and raise confidence through the normal IncidentUpdated path.
 */
#[AsMessageHandler]
final readonly class ExternalSourcesTickHandler
{
    public const string FOUND_BY = 'rss';

    public function __construct(
        private FeedRegistry $feeds,
        private IncidentFeedMatcher $matcher,
        private IncidentRepository $incidents,
        private ExternalSourceRepository $sources,
        private ExternalSourceRecorder $recorder,
        private PlaceNamer $placeNamer,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExternalSourcesTick $tick): void
    {
        if ([] === $this->feeds->feeds()) {
            return;
        }
        $open = $this->incidents->findOpen();
        if ([] === $open) {
            return;
        }

        $items = $this->feeds->items();
        if ([] === $items) {
            return;
        }

        $added = 0;
        foreach ($open as $incident) {
            foreach ($this->matcher->match($incident, $this->placeName($incident), $items) as $match) {
                if ($this->sources->existsByUrl($incident, $match->item->url)) {
                    continue;
                }
                $this->recorder->record(
                    $incident,
                    $match->item->url,
                    $match->item->title,
                    $match->item->feed->kind,
                    // Textual match strength discounts the feed's nominal credibility a little.
                    $match->item->feed->credibility * (0.8 + 0.2 * $match->score),
                    self::FOUND_BY,
                    $match->item->feed->name,
                    $match->excerpt(),
                    $match->item->publishedAt,
                );
                ++$added;
            }
        }

        if ($added > 0) {
            $this->logger->info('External sources: {n} new feed items attached to incidents', ['n' => $added]);
        }
    }

    private function placeName(Incident $incident): string
    {
        $key = 'incident.place.'.$incident->getId()->toRfc4122();

        /** @var string */
        return $this->cache->get($key, function (ItemInterface $item) use ($incident): string {
            $item->expiresAfter(3600);

            return $this->placeNamer->nameFor($incident->getCentroid());
        });
    }
}
