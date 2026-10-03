<?php

declare(strict_types=1);

namespace App\Guidance\View;

use App\Guidance\Model\Procedure;
use App\Reporting\Enum\ReportType;

final class ProcedureView
{
    /** @return array{id: string, title: string, summary: string, steps: list<string>, appliesTo: list<string>, priority: int} */
    public static function toArray(Procedure $p): array
    {
        return [
            'id' => $p->id,
            'title' => $p->title,
            'summary' => $p->summary,
            'steps' => $p->steps,
            'appliesTo' => array_map(static fn (ReportType $t) => $t->value, $p->appliesTo),
            'priority' => $p->priority,
        ];
    }

    /**
     * @param list<Procedure> $procedures
     *
     * @return list<array<string, mixed>>
     */
    public static function list(array $procedures): array
    {
        return array_map(self::toArray(...), $procedures);
    }
}
