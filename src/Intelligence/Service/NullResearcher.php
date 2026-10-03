<?php

declare(strict_types=1);

namespace App\Intelligence\Service;

use App\Incident\Entity\Incident;
use App\Intelligence\Model\ResearchResult;

/** RESEARCH_PROVIDER=none: research is skipped, incidents can still be confirmed by operators or the simulator. */
final class NullResearcher implements ResearcherInterface
{
    public function name(): string
    {
        return 'none';
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function research(Incident $incident, string $placeName): ResearchResult
    {
        return new ResearchResult('', []);
    }
}
