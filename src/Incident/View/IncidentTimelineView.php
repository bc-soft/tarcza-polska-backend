<?php

declare(strict_types=1);

namespace App\Incident\View;

use App\Incident\Model\TimelineEntry;

final class IncidentTimelineView
{
    /** Detail keys that identify an operator or a single reporter; never serialised for citizens. */
    private const array INTERNAL_DETAILS = ['createdBy', 'cell', 'reportId', 'photoId'];

    /** @return array{type: string, label: string, at: string, details: array<string, scalar|null>} */
    public static function toArray(TimelineEntry $entry, bool $public = false): array
    {
        return [
            'type' => $entry->type->value,
            'label' => $entry->label(),
            'at' => $entry->at->format(\DATE_ATOM),
            'details' => $public ? array_diff_key($entry->details, array_flip(self::INTERNAL_DETAILS)) : $entry->details,
        ];
    }

    /**
     * @param list<TimelineEntry> $entries
     *
     * @return list<array{type: string, label: string, at: string, details: array<string, scalar|null>}>
     */
    public static function list(array $entries, bool $public = false): array
    {
        return array_map(static fn (TimelineEntry $e) => self::toArray($e, $public), $entries);
    }
}
