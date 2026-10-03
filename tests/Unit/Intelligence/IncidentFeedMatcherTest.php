<?php

declare(strict_types=1);

namespace App\Tests\Unit\Intelligence;

use App\Incident\Entity\Incident;
use App\Intelligence\Model\FeedDefinition;
use App\Intelligence\Model\FeedItem;
use App\Intelligence\Service\Feed\IncidentFeedMatcher;
use App\Reporting\Enum\ReportType;
use App\Shared\Geo\Point;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IncidentFeedMatcherTest extends TestCase
{
    private IncidentFeedMatcher $matcher;
    private FeedDefinition $feed;

    protected function setUp(): void
    {
        $this->matcher = new IncidentFeedMatcher();
        $this->feed = new FeedDefinition('Test', 'https://example.test/rss', 'media', 0.7);
    }

    #[Test]
    public function matchesTypeAndPlaceWithinTheTimeWindow(): void
    {
        $incident = new Incident(ReportType::PowerOutage, new Point(52.41, 16.9), '891e24aa0b3ffff');
        $now = new DateTimeImmutable();
        $items = [
            $this->item('Awaria prądu na Jeżycach, kilka tysięcy odbiorców bez zasilania', 'Enea Operator pracuje nad usunięciem awarii w Poznaniu.', $now->modify('-10 minutes'), 'https://x.test/1'),
            $this->item('Korki na A2 pod Poznaniem', 'Zderzenie dwóch aut.', $now->modify('-5 minutes'), 'https://x.test/2'),
            $this->item('Blackout w Krakowie', 'Bez prądu kilka dzielnic.', $now->modify('-5 minutes'), 'https://x.test/3'),
            $this->item('Awaria prądu na Jeżycach', 'Stary artykuł.', $now->modify('-3 days'), 'https://x.test/4'),
        ];

        $matches = $this->matcher->match($incident, 'Jeżyce, Poznań', $items, $now);

        self::assertCount(1, $matches);
        self::assertSame('https://x.test/1', $matches[0]->item->url);
        self::assertGreaterThanOrEqual(0.8, $matches[0]->score, 'title mentions both the type and the place, two place tokens hit');
        self::assertContains('prąd', $matches[0]->typeHits);
    }

    #[Test]
    public function placeTokensStemPolishDeclension(): void
    {
        $tokens = IncidentFeedMatcher::placeTokens('Jeżyce, Poznań');

        self::assertSame(['jeżyc', 'pozna'], $tokens);
        self::assertSame([], IncidentFeedMatcher::placeTokens('52.4101, 16.8968'));
    }

    #[Test]
    public function itemsWithoutDateAreAccepted(): void
    {
        $incident = new Incident(ReportType::WaterOutage, new Point(52.41, 16.9), '891e24aa0b3ffff');
        $items = [$this->item('Aquanet: brak wody na Wildzie w Poznaniu', '', null, 'https://x.test/w')];

        self::assertCount(1, $this->matcher->match($incident, 'Wilda, Poznań', $items));
    }

    #[Test]
    public function everyTypeHasKeywords(): void
    {
        foreach (ReportType::cases() as $type) {
            self::assertNotEmpty(IncidentFeedMatcher::keywordsFor($type), $type->value);
        }
    }

    private function item(string $title, string $summary, ?DateTimeImmutable $at, string $url): FeedItem
    {
        return new FeedItem($this->feed, $url, $title, $summary, $at);
    }
}
