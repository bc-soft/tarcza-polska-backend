<?php

declare(strict_types=1);

namespace App\Intelligence\Model;

/** A feed item that looks like evidence for an incident, with the matched keywords for explainability. */
final readonly class FeedMatch
{
    /**
     * @param list<string> $typeHits
     * @param list<string> $placeHits
     */
    public function __construct(
        public FeedItem $item,
        /** 0..1 strength of the textual match (not credibility: that comes from the feed). */
        public float $score,
        public array $typeHits,
        public array $placeHits,
    ) {
    }

    public function excerpt(): string
    {
        $summary = $this->item->summary;

        return mb_strlen($summary) > 280 ? mb_substr($summary, 0, 277).'...' : $summary;
    }
}
