<?php

declare(strict_types=1);

namespace App\Fuel\Dto;

use App\Fuel\Enum\FuelType;
use Symfony\Component\Validator\Constraints as Assert;

/** Citizen confirmation at a station: these fuel types are available / missing right now. */
final readonly class ConfirmFuelStatusRequest
{
    /** @param list<FuelType> $fuelTypes */
    public function __construct(
        #[Assert\Count(min: 1, max: 4)]
        public array $fuelTypes,
        public bool $available,
        #[Assert\Length(max: 500)]
        public ?string $comment = null,
    ) {
    }
}
