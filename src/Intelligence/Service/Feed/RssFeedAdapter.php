<?php

declare(strict_types=1);

namespace App\Intelligence\Service\Feed;

use App\Intelligence\Model\FeedDefinition;
use App\Intelligence\Model\FeedItem;
use DateTimeImmutable;
use Exception;
use SimpleXMLElement;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * RSS 2.0 and Atom over HTTP. Tolerant parser: a broken item is skipped, a broken feed yields an empty list
 * (the registry logs it). Entity expansion is disabled, so hostile feeds cannot bomb the parser.
 */
final readonly class RssFeedAdapter implements FeedAdapterInterface
{
    public const int MAX_ITEMS = 60;

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function supports(FeedDefinition $feed): bool
    {
        return str_starts_with($feed->url, 'http://') || str_starts_with($feed->url, 'https://');
    }

    public function fetch(FeedDefinition $feed): array
    {
        $xml = $this->httpClient->request('GET', $feed->url, [
            'headers' => ['User-Agent' => 'TarczaPolska/0.1 (+external-sources)', 'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml'],
            'timeout' => 10,
            'max_duration' => 15,
        ])->getContent();

        return $this->parse($feed, $xml);
    }

    /** @return list<FeedItem> */
    public function parse(FeedDefinition $feed, string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            // No LIBXML_NOENT: entities are never substituted, no network access while parsing.
            $root = simplexml_load_string($xml, SimpleXMLElement::class, \LIBXML_NOCDATA | \LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$root instanceof SimpleXMLElement) {
            return [];
        }

        $items = [];
        if (isset($root->channel)) {
            foreach ($root->channel->item as $item) {
                $parsed = $this->rssItem($feed, $item);
                if (null !== $parsed) {
                    $items[] = $parsed;
                }
            }
        } elseif ('feed' === $root->getName()) {
            foreach ($root->entry as $entry) {
                $parsed = $this->atomEntry($feed, $entry);
                if (null !== $parsed) {
                    $items[] = $parsed;
                }
            }
        }

        return \array_slice($items, 0, self::MAX_ITEMS);
    }

    private function rssItem(FeedDefinition $feed, SimpleXMLElement $item): ?FeedItem
    {
        $url = trim((string) $item->link);
        if ('' === $url && isset($item->guid)) {
            $url = trim((string) $item->guid);
        }
        $title = self::clean((string) $item->title);
        if ('' === $url || '' === $title) {
            return null;
        }

        return new FeedItem(
            $feed,
            $url,
            $title,
            self::clean((string) $item->description),
            self::date((string) $item->pubDate),
        );
    }

    private function atomEntry(FeedDefinition $feed, SimpleXMLElement $entry): ?FeedItem
    {
        $url = '';
        foreach ($entry->link as $link) {
            $rel = (string) ($link['rel'] ?? 'alternate');
            if ('alternate' === $rel || '' === $url) {
                $url = trim((string) $link['href']);
            }
        }
        $title = self::clean((string) $entry->title);
        if ('' === $url || '' === $title) {
            return null;
        }
        $summary = isset($entry->summary) ? (string) $entry->summary : (string) $entry->content;

        return new FeedItem(
            $feed,
            $url,
            $title,
            self::clean($summary),
            self::date((string) ($entry->published ?? $entry->updated)),
        );
    }

    private static function clean(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private static function date(string $raw): ?DateTimeImmutable
    {
        if ('' === trim($raw)) {
            return null;
        }
        try {
            return new DateTimeImmutable($raw);
        } catch (Exception) {
            return null;
        }
    }
}
