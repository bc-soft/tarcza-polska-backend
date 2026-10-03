<?php

declare(strict_types=1);

namespace App\Guidance\Model;

use App\Reporting\Enum\ReportType;

/**
 * A short, offline-safe checklist the app can show without network ("co robić, gdy...").
 */
final readonly class Procedure
{
    /**
     * @param list<string>     $steps
     * @param list<ReportType> $appliesTo empty = general
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $summary,
        public array $steps,
        public array $appliesTo = [],
        public int $priority = 50,
    ) {
    }
}
