<?php

declare(strict_types=1);

namespace App\Tests\Unit\Intelligence;

use App\Intelligence\Model\FeedDefinition;
use App\Intelligence\Service\Feed\RssFeedAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RssFeedAdapterTest extends TestCase
{
    private const string RSS = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0"><channel><title>Test</title>
          <item>
            <title><![CDATA[Awaria prądu na Jeżycach w Poznaniu]]></title>
            <link>https://example.test/awaria-jezyce</link>
            <description><![CDATA[<p>Bez prądu jest ok. <b>2 tys.</b> odbiorców &amp; trwa naprawa.</p>]]></description>
            <pubDate>Sat, 03 Oct 2026 14:50:00 +0200</pubDate>
          </item>
          <item><title>Bez linku</title><description>pomijany</description></item>
          <item><title>Z guid</title><guid>https://example.test/guid-only</guid><pubDate>not a date</pubDate></item>
        </channel></rss>
        XML;

    private const string ATOM = <<<'XML'
        <?xml version="1.0" encoding="utf-8"?>
        <feed xmlns="http://www.w3.org/2005/Atom">
          <title>Atom test</title>
          <entry>
            <title>Komunikat o awarii wodociągu</title>
            <link rel="self" href="https://example.test/self"/>
            <link rel="alternate" href="https://example.test/woda"/>
            <summary>Brak wody na Wildzie do godz. 18.</summary>
            <updated>2026-10-03T12:00:00Z</updated>
          </entry>
        </feed>
        XML;

    #[Test]
    public function parsesRssWithCdataHtmlAndMissingFields(): void
    {
        $feed = new FeedDefinition('Test', 'https://example.test/rss', 'media', 0.7);
        $items = new RssFeedAdapter(new MockHttpClient())->parse($feed, self::RSS);

        self::assertCount(2, $items);
        self::assertSame('Awaria prądu na Jeżycach w Poznaniu', $items[0]->title);
        self::assertSame('https://example.test/awaria-jezyce', $items[0]->url);
        self::assertSame('Bez prądu jest ok. 2 tys. odbiorców & trwa naprawa.', $items[0]->summary);
        self::assertSame('2026-10-03T14:50:00+02:00', $items[0]->publishedAt?->format(\DATE_ATOM));
        self::assertSame('https://example.test/guid-only', $items[1]->url);
        self::assertNull($items[1]->publishedAt);
        self::assertSame($feed, $items[0]->feed);
    }

    #[Test]
    public function parsesAtomPreferringTheAlternateLink(): void
    {
        $feed = new FeedDefinition('Atom', 'https://example.test/atom', 'official', 0.9);
        $items = new RssFeedAdapter(new MockHttpClient())->parse($feed, self::ATOM);

        self::assertCount(1, $items);
        self::assertSame('https://example.test/woda', $items[0]->url);
        self::assertSame('Komunikat o awarii wodociągu', $items[0]->title);
        self::assertSame('2026-10-03T12:00:00+00:00', $items[0]->publishedAt?->format(\DATE_ATOM));
    }

    #[Test]
    public function fetchesOverHttpAndSurvivesGarbage(): void
    {
        $client = new MockHttpClient([new MockResponse(self::RSS), new MockResponse('<<not xml')]);
        $adapter = new RssFeedAdapter($client);
        $feed = new FeedDefinition('Test', 'https://example.test/rss', 'media', 0.7);

        self::assertTrue($adapter->supports($feed));
        self::assertCount(2, $adapter->fetch($feed));
        self::assertSame([], $adapter->fetch($feed));
    }

    #[Test]
    public function configRowsAreNormalised(): void
    {
        $feed = FeedDefinition::fromConfig(['url' => 'https://x.test/f', 'credibility' => 1.7]);

        self::assertSame('https://x.test/f', $feed->name);
        self::assertSame('other', $feed->kind);
        self::assertSame(1.0, $feed->credibility);
    }
}
