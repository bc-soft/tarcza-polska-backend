<?php

declare(strict_types=1);

namespace App\Confidence\Model;

use App\Incident\Enum\ConfidenceLevel;

final readonly class ConfidenceResult
{
    /** @param array<string, mixed> $breakdown */
    public function __construct(
        public float $score,
        public ConfidenceLevel $level,
        public array $breakdown,
    ) {
    }
}
