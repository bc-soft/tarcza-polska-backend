<?php

declare(strict_types=1);

namespace App\Reporting\Dto;

use App\Reporting\Enum\ReportType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateReportRequest
{
    public function __construct(
        public ReportType $type,
        #[Assert\Range(min: -90, max: 90)]
        public float $lat,
        #[Assert\Range(min: -180, max: 180)]
        public float $lng,
        #[Assert\Length(max: 1000)]
        public ?string $description = null,
    ) {
    }
}
