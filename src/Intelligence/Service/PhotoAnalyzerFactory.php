<?php

declare(strict_types=1);

namespace App\Intelligence\Service;

/**
 * Same switch as ResearcherFactory: RESEARCH_PROVIDER=openai gives vision analysis, anything else none
 * (a Claude vision analyzer can be added here later without touching the Reporting module).
 */
final readonly class PhotoAnalyzerFactory
{
    public function __construct(
        private OpenAiPhotoAnalyzer $openAi,
        private string $researchProvider,
    ) {
    }

    public function create(): PhotoAnalyzerInterface
    {
        return match (strtolower(trim($this->researchProvider))) {
            'openai' => $this->openAi,
            default => new NullPhotoAnalyzer(),
        };
    }
}
