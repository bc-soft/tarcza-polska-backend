<?php

declare(strict_types=1);

namespace App\Intelligence\Service;

use App\Incident\Entity\Incident;
use App\Intelligence\Model\ResearchResult;

interface ResearcherInterface
{
    public function isEnabled(): bool;

    public function research(Incident $incident, string $placeName): ResearchResult;
}
