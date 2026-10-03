<?php

declare(strict_types=1);

namespace App\Intelligence\Model;

use InvalidArgumentException;

/** One configured RSS/Atom feed (config/packages/external_sources.yaml). */
final readonly class FeedDefinition
{
    public function __construct(
        public string $name,
        public string $url,
        /** official | infrastructure_operator | media | social | other */
        public string $kind,
        public float $credibility,
    ) {
    }

    /** @param array{name?: mixed, url?: mixed, kind?: mixed, credibility?: mixed} $row */
    public static function fromConfig(array $row): self
    {
        $url = \is_string($row['url'] ?? null) ? $row['url'] : '';
        if ('' === $url) {
            throw new InvalidArgumentException('External source feed without url');
        }

        return new self(
            name: \is_string($row['name'] ?? null) && '' !== $row['name'] ? $row['name'] : $url,
            url: $url,
            kind: \is_string($row['kind'] ?? null) ? $row['kind'] : 'other',
            credibility: is_numeric($row['credibility'] ?? null) ? max(0.0, min(1.0, (float) $row['credibility'])) : 0.5,
        );
    }
}
