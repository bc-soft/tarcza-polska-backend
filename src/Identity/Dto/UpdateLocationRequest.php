<?php

declare(strict_types=1);

namespace App\Identity\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateLocationRequest
{
    public function __construct(
        #[Assert\Range(min: -90, max: 90)]
        public float $lat,
        #[Assert\Range(min: -180, max: 180)]
        public float $lng,
        #[Assert\PositiveOrZero]
        public ?float $accuracyMeters = null,
    ) {
    }
}
