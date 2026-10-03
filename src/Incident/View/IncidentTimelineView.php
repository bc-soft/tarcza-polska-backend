<?php

declare(strict_types=1);

namespace App\Incident\View;

use App\Incident\Model\TimelineEntry;

final class IncidentTimelineView
{
    /** @return array{type: string, label: string, at: string, details: array<string, scalar|null>} */
    public static function toArray(TimelineEntry $entry): array
    {
        return [
            'type' => $entry->type->value,
            'label' => $entry->label(),
            'at' => $entry->at->format(\DATE_ATOM),
            'details' => $entry->details,
        ];
    }

    /**
     * @param list<TimelineEntry> $entries
     *
     * @return list<array{type: string, label: string, at: string, details: array<string, scalar|null>}>
     */
    public static function list(array $entries): array
    {
        return array_map(self::toArray(...), $entries);
    }
}
