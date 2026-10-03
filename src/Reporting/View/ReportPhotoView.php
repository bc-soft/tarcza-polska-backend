<?php

declare(strict_types=1);

namespace App\Reporting\View;

use App\Reporting\Entity\ReportPhoto;

final class ReportPhotoView
{
    /**
     * Citizen response right after upload.
     *
     * @return array<string, mixed>
     */
    public static function accepted(ReportPhoto $photo): array
    {
        return [
            'photoId' => $photo->getId()->toRfc4122(),
            'reportId' => $photo->getReport()->getId()->toRfc4122(),
            'status' => 'processing',
            'width' => $photo->getWidth(),
            'height' => $photo->getHeight(),
            'bytes' => $photo->getBytes(),
            'createdAt' => $photo->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    /**
     * Operator view with analysis.
     *
     * @return array<string, mixed>
     */
    public static function command(ReportPhoto $photo, string $fileUrl): array
    {
        return [
            'id' => $photo->getId()->toRfc4122(),
            'reportId' => $photo->getReport()->getId()->toRfc4122(),
            'url' => $fileUrl,
            'width' => $photo->getWidth(),
            'height' => $photo->getHeight(),
            'bytes' => $photo->getBytes(),
            'createdAt' => $photo->getCreatedAt()->format(\DATE_ATOM),
            'analyzedAt' => $photo->getAnalyzedAt()?->format(\DATE_ATOM),
            'analyzer' => $photo->getAnalyzer(),
            'relevant' => $photo->isRelevant(),
            'matchesType' => $photo->matchesType(),
            'description' => $photo->getDescription(),
            'unsafe' => $photo->isUnsafe(),
            'unsafeReason' => $photo->getUnsafeReason(),
            'confidence' => null === $photo->getAnalysisConfidence() ? null : round($photo->getAnalysisConfidence(), 2),
        ];
    }
}
