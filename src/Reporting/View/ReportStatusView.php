<?php

declare(strict_types=1);

namespace App\Reporting\View;

use App\Reporting\Entity\Report;

/** GET /api/v1/reports/{id} (OpenAPI: ReportStatusView). */
final class ReportStatusView
{
    /** @return array<string, mixed> */
    public static function toArray(Report $report): array
    {
        $incident = $report->getIncident();

        return [
            'reportId' => $report->getId()->toRfc4122(),
            'type' => $report->getType()->value,
            'typeLabel' => $report->getType()->label(),
            'createdAt' => $report->getCreatedAt()->format(\DATE_ATOM),
            'incident' => null === $incident ? null : [
                'id' => $incident->getId()->toRfc4122(),
                'type' => $incident->getType()->value,
                'typeLabel' => $incident->getType()->label(),
                'status' => $incident->getStatus()->value,
                'statusLabel' => $incident->getStatus()->label(),
                'confidenceLevel' => $incident->getConfidenceLevel()->value,
                'confidenceLabel' => $incident->getConfidenceLevel()->label(),
                'confidenceScore' => round($incident->getConfidenceScore(), 2),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function accepted(Report $report): array
    {
        return [
            'reportId' => $report->getId()->toRfc4122(),
            'h3Cell' => $report->getH3Cell(),
            'createdAt' => $report->getCreatedAt()->format(\DATE_ATOM),
        ];
    }
}
