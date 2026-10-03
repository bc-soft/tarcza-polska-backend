<?php

declare(strict_types=1);

namespace App\Identity\Dto;

final readonly class UpdatePreferencesRequest
{
    public function __construct(
        /** Receive the "are you still in this area?" reminder when the position is stale. Default on. */
        public bool $locationRefresh,
    ) {
    }
}
