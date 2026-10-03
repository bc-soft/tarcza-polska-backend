<?php

declare(strict_types=1);

namespace App\Shelter\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateShelterRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 160)]
        public string $name,
        #[Assert\Range(min: -90, max: 90)]
        public float $lat,
        #[Assert\Range(min: -180, max: 180)]
        public float $lng,
        #[Assert\Length(max: 255)]
        public ?string $address = null,
        #[Assert\PositiveOrZero]
        public ?int $capacity = null,
    ) {
    }
}
