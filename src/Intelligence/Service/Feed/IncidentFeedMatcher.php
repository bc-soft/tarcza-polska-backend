<?php

declare(strict_types=1);

namespace App\Intelligence\Service\Feed;

use App\Incident\Entity\Incident;
use App\Intelligence\Model\FeedItem;
use App\Intelligence\Model\FeedMatch;
use App\Reporting\Enum\ReportType;
use DateTimeImmutable;

/**
 * Deterministic keyword matcher: an item is evidence for an incident when it mentions the problem type
 * AND the place, and was published around the incident's lifetime. No LLM: cheap enough to run every 5 min.
 */
final class IncidentFeedMatcher
{
    public const int LOOKBACK_HOURS = 6;
    public const int MIN_PLACE_TOKEN_LENGTH = 4;

    /** Polish stems per type (lowercase, matched as substrings). */
    private const array TYPE_KEYWORDS = [
        'power_outage' => ['prąd', 'prad', 'zasilan', 'energet', 'blackout', 'bez światła', 'bez swiatla', 'awaria sieci', 'enea', 'tauron', 'pge dystrybucja', 'energa', 'stoen'],
        'water_outage' => ['wod', 'wodociąg', 'wodociag', 'aquanet', 'mpwik', 'beczkowóz', 'beczkowoz', 'magistral'],
        'fuel_shortage' => ['paliw', 'stacj', 'benzyn', 'diesel', 'orlen', 'tankow'],
        'road_blocked' => ['nieprzejezdn', 'zablokowan', 'objazd', 'utrudnieni', 'zamknięt', 'zamkniet', 'wypadek', 'kolizj', 'drzewo na', 'zalan'],
        'shelter_issue' => ['schron', 'ukrycia', 'ewakuac'],
        'other_threat' => ['alarm', 'syren', 'zagrożen', 'zagrozen', 'ewakuac', 'rcb', 'pożar', 'pozar', 'eksploz', 'wybuch', 'skażen', 'skazen'],
    ];

    /**
     * @param list<FeedItem> $items
     *
     * @return list<FeedMatch> best first
     */
    public function match(Incident $incident, string $placeName, array $items, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $keywords = self::TYPE_KEYWORDS[$incident->getType()->value] ?? [];
        $placeTokens = self::placeTokens($placeName);
        if ([] === $keywords || [] === $placeTokens) {
            return [];
        }
        $notBefore = $incident->getStartedAt()->modify(\sprintf('-%d hours', self::LOOKBACK_HOURS));

        $matches = [];
        foreach ($items as $item) {
            if (null !== $item->publishedAt && ($item->publishedAt < $notBefore || $item->publishedAt > $now->modify('+1 hour'))) {
                continue;
            }
            $text = $item->text();
            $title = mb_strtolower($item->title);

            $typeHits = array_values(array_filter($keywords, static fn (string $k) => str_contains($text, $k)));
            if ([] === $typeHits) {
                continue;
            }
            $placeHits = array_values(array_filter($placeTokens, static fn (string $t) => str_contains($text, $t)));
            if ([] === $placeHits) {
                continue;
            }

            $score = 0.5
                + (\count($placeHits) > 1 ? 0.2 : 0.0)
                + (self::any($title, $typeHits) ? 0.15 : 0.0)
                + (self::any($title, $placeHits) ? 0.15 : 0.0);

            $matches[] = new FeedMatch($item, min(1.0, $score), $typeHits, $placeHits);
        }

        usort($matches, static fn (FeedMatch $a, FeedMatch $b) => $b->score <=> $a->score);

        return $matches;
    }

    /** @return list<string> lowercase tokens of "Jeżyce, Poznań" worth matching (district, city) */
    public static function placeTokens(string $placeName): array
    {
        $tokens = [];
        foreach (preg_split('/[\s,;\/]+/u', mb_strtolower($placeName)) ?: [] as $token) {
            $token = trim($token, " \t.-()");
            if (mb_strlen($token) < self::MIN_PLACE_TOKEN_LENGTH || is_numeric(str_replace('.', '', $token))) {
                continue;
            }
            // Stem crude Polish declension: "Poznań" also matches "Poznaniu", "Jeżyce" matches "Jeżycach".
            $tokens[] = mb_substr($token, 0, max(self::MIN_PLACE_TOKEN_LENGTH, mb_strlen($token) - 1));
        }

        return array_values(array_unique($tokens));
    }

    /** @param list<string> $needles */
    private static function any(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if (str_contains($haystack, $n)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public static function keywordsFor(ReportType $type): array
    {
        return self::TYPE_KEYWORDS[$type->value] ?? [];
    }
}
