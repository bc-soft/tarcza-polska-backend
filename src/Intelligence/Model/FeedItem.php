<?php

declare(strict_types=1);

namespace App\Intelligence\Model;

use DateTimeImmutable;

/** One entry of a fetched feed, normalised across RSS 2.0 and Atom. */
final readonly class FeedItem
{
    public function __construct(
        public FeedDefinition $feed,
        public string $url,
        public string $title,
        public string $summary,
        public ?DateTimeImmutable $publishedAt,
    ) {
    }

    public function text(): string
    {
        return mb_strtolower($this->title.' '.$this->summary);
    }
}
