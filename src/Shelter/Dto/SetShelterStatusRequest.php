<?php

declare(strict_types=1);

namespace App\Shelter\Dto;

use App\Shelter\Enum\ShelterStatus;

/** Operator override of the aggregate status (does not count as a citizen confirmation). */
final readonly class SetShelterStatusRequest
{
    public function __construct(
        public ShelterStatus $status,
    ) {
    }
}
