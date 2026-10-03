<?php

declare(strict_types=1);

namespace App\Alerting\Message;

/** Domain event: deliver the alert to devices inside the area. */
final readonly class AlertPublished
{
    public function __construct(public string $alertId)
    {
    }
}
