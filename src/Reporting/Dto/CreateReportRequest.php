<?php

declare(strict_types=1);

namespace App\Reporting\Dto;

use App\Fuel\Enum\FuelType;
use App\Reporting\Enum\ReportType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateReportRequest
{
    /** @param list<FuelType>|null $fuelTypes */
    public function __construct(
        public ReportType $type,
        #[Assert\Range(min: -90, max: 90)]
        public float $lat,
        #[Assert\Range(min: -180, max: 180)]
        public float $lng,
        #[Assert\Length(max: 1000)]
        public ?string $description = null,
        /** Point types (fuel_shortage, shelter_issue): the station / shelter the report is about. Omitted = nearest one. */
        #[Assert\Uuid]
        public ?string $poiId = null,
        /** fuel_shortage only: which fuels are missing (pb95, pb98, diesel, lpg). Required for that type. */
        #[Assert\Count(max: 4)]
        public ?array $fuelTypes = null,
    ) {
    }
}
