<?php

declare(strict_types=1);

namespace App\Verification\Message;

/** Command: plan and send the next verification wave for an incident (no-op if one is open). */
final readonly class ScheduleVerificationWave
{
    public function __construct(public string $incidentId)
    {
    }
}
