<?php

declare(strict_types=1);

namespace App\Reporting\Model;

/**
 * Result of the vision analysis of a report photo. Produced by a PhotoAnalyzerInterface implementation.
 */
final readonly class PhotoAnalysis
{
    public function __construct(
        /** Does the picture show something related to a civil-infrastructure problem at all? */
        public bool $relevant,
        /** Does it match the type the citizen reported (e.g. a dark street for power_outage)? */
        public bool $matchesType,
        public string $description,
        public bool $unsafe,
        public ?string $unsafeReason,
        /** 0..1 how sure the analyzer is about relevant/matchesType. */
        public float $confidence,
    ) {
    }

    /** @param array<string, mixed> $data decoded JSON matching PhotoAnalysisSchema::json() */
    public static function fromArray(array $data): self
    {
        $reason = $data['unsafeReason'] ?? null;

        return new self(
            relevant: (bool) ($data['relevant'] ?? false),
            matchesType: (bool) ($data['matchesType'] ?? false),
            description: \is_string($data['description'] ?? null) ? $data['description'] : '',
            unsafe: (bool) ($data['unsafe'] ?? false),
            unsafeReason: \is_string($reason) && '' !== $reason ? $reason : null,
            confidence: is_numeric($data['confidence'] ?? null) ? (float) $data['confidence'] : 0.0,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'relevant' => $this->relevant,
            'matchesType' => $this->matchesType,
            'description' => $this->description,
            'unsafe' => $this->unsafe,
            'unsafeReason' => $this->unsafeReason,
            'confidence' => round($this->confidence, 2),
        ];
    }
}
