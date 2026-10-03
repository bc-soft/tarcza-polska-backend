<?php

declare(strict_types=1);

namespace App\Intelligence\Message;

/** Command: run AI research for an incident (public sources, operator statements, media). */
final readonly class ResearchIncident
{
    public function __construct(public string $incidentId)
    {
    }
}
