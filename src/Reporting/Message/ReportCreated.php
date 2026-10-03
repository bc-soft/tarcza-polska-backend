<?php

declare(strict_types=1);

namespace App\Reporting\Message;

/** Domain event: a new report has been persisted. Consumed by the Incident module. */
final readonly class ReportCreated
{
    public function __construct(public string $reportId)
    {
    }
}
