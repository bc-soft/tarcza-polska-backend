<?php

declare(strict_types=1);

namespace App\Intelligence\Service;

use App\Incident\Entity\Incident;
use App\Intelligence\Model\ResearchResult;

/**
 * A research provider. The concrete one is chosen by RESEARCH_PROVIDER (see ResearcherFactory).
 */
interface ResearcherInterface
{
    /** Human-readable provider id for logs, e.g. "openai:gpt-5". */
    public function name(): string;

    /** False when the provider has no credentials; the handler then skips research with a log entry. */
    public function isEnabled(): bool;

    public function research(Incident $incident, string $placeName): ResearchResult;
}
