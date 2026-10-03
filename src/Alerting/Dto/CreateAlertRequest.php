<?php

declare(strict_types=1);

namespace App\Alerting\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateAlertRequest
{
    /** @param array<string, mixed>|null $area */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 160)]
        public string $title,
        #[Assert\NotBlank]
        #[Assert\Length(max: 2000)]
        public string $body,
        #[Assert\Choice(['info', 'warning', 'danger'])]
        public string $severity = 'warning',
        /** Incident whose current area becomes the alert area (if `area` is omitted). */
        #[Assert\Uuid]
        public ?string $incidentId = null,
        /** Explicit GeoJSON Polygon / MultiPolygon. */
        public ?array $area = null,
        #[Assert\Range(min: 5, max: 1440)]
        public int $ttlMinutes = 180,
    ) {
    }
}
