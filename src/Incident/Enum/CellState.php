<?php

declare(strict_types=1);

namespace App\Incident\Enum;

/** State of one hexagon of an incident's dynamic area. */
enum CellState: string
{
    case Unknown = 'unknown';
    case Positive = 'positive';
    case Negative = 'negative';
}
