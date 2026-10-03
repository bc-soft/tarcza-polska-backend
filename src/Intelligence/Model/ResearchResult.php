<?php

declare(strict_types=1);

namespace App\Intelligence\Model;

final readonly class ResearchResult
{
    /** @param list<ResearchFinding> $findings */
    public function __construct(
        public string $summary,
        public array $findings,
        public ?string $suggestedVerificationQuestion = null,
    ) {
    }

    /** @return list<ResearchFinding> */
    public function confirming(): array
    {
        return array_values(array_filter($this->findings, static fn (ResearchFinding $f) => $f->confirmsIncident));
    }
}
