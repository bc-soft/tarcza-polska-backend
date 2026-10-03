<?php

declare(strict_types=1);

namespace App\Intelligence\Service;

use App\Reporting\Enum\ReportType;
use App\Reporting\Model\PhotoAnalysis;

/**
 * Vision analysis of a citizen photo: relevance, match with the reported type, moderation.
 * The concrete provider follows RESEARCH_PROVIDER (see PhotoAnalyzerFactory).
 */
interface PhotoAnalyzerInterface
{
    public function name(): string;

    public function isEnabled(): bool;

    public function analyze(string $imageBytes, string $mime, ReportType $reportedType): PhotoAnalysis;
}
