<?php

declare(strict_types=1);

namespace App\Intelligence\Service;

use App\Reporting\Enum\ReportType;
use App\Reporting\Model\PhotoAnalysis;

/** No vision provider configured: photos are stored and shown to operators without an automatic verdict. */
final class NullPhotoAnalyzer implements PhotoAnalyzerInterface
{
    public function name(): string
    {
        return 'none';
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function analyze(string $imageBytes, string $mime, ReportType $reportedType): PhotoAnalysis
    {
        return new PhotoAnalysis(false, false, '', false, null, 0.0);
    }
}
