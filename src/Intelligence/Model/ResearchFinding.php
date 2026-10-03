<?php

declare(strict_types=1);

namespace App\Intelligence\Model;

use DateTimeImmutable;

final readonly class ResearchFinding
{
    public function __construct(
        public string $url,
        public string $title,
        public string $publisher,
        public string $kind,
        public float $credibility,
        public string $excerpt,
        public ?DateTimeImmutable $publishedAt,
        public bool $confirmsIncident,
    ) {
    }
}
