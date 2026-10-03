<?php

declare(strict_types=1);

namespace App\Shelter\Dto;

use App\Shelter\Enum\ShelterOccupancy;
use App\Shelter\Enum\ShelterStatus;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ConfirmShelterStatusRequest
{
    public function __construct(
        public ShelterStatus $status,
        #[Assert\Length(max: 500)]
        public ?string $comment = null,
        /** Only meaningful with status=open: plenty | limited | full. */
        public ?ShelterOccupancy $occupancy = null,
    ) {
    }
}
