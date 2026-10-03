<?php

declare(strict_types=1);

namespace App\Intelligence\Service\Feed;

use App\Intelligence\Model\FeedDefinition;
use App\Intelligence\Model\FeedItem;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

/**
 * Knows the configured feeds, picks an adapter for each and caches the merged item list for one poll interval,
 * so a tick with many open incidents fetches every feed once.
 */
final class FeedRegistry
{
    /** @var list<FeedDefinition> */
    private array $feeds;

    /**
     * @param iterable<FeedAdapterInterface>                                            $adapters
     * @param list<array{name?: mixed, url?: mixed, kind?: mixed, credibility?: mixed}> $externalSourceFeeds
     */
    public function __construct(
        #[AutowireIterator(FeedAdapterInterface::TAG)]
        private readonly iterable $adapters,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        array $externalSourceFeeds,
        private readonly int $externalSourcesPollMinutes,
    ) {
        $this->feeds = array_map(FeedDefinition::fromConfig(...), $externalSourceFeeds);
    }

    /** @return list<FeedDefinition> */
    public function feeds(): array
    {
        return $this->feeds;
    }

    /** @return list<FeedItem> items of every reachable feed, cached for the poll interval */
    public function items(): array
    {
        /** @var list<FeedItem> */
        return $this->cache->get('external_sources.items', function (ItemInterface $item): array {
            $item->expiresAfter(max(60, $this->externalSourcesPollMinutes * 60 - 30));

            return $this->fetchAll();
        });
    }

    /** @return list<FeedItem> */
    public function fetchAll(): array
    {
        $items = [];
        foreach ($this->feeds as $feed) {
            $adapter = $this->adapterFor($feed);
            if (null === $adapter) {
                $this->logger->warning('No feed adapter for {url}', ['url' => $feed->url]);
                continue;
            }
            try {
                $fetched = $adapter->fetch($feed);
                $this->logger->info('Feed {name}: {n} items', ['name' => $feed->name, 'n' => \count($fetched)]);
                array_push($items, ...$fetched);
            } catch (Throwable $e) {
                $this->logger->warning('Feed {name} failed: {error}', ['name' => $feed->name, 'error' => $e->getMessage()]);
            }
        }

        return $items;
    }

    private function adapterFor(FeedDefinition $feed): ?FeedAdapterInterface
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($feed)) {
                return $adapter;
            }
        }

        return null;
    }
}
