<?php

declare(strict_types=1);

namespace App\Verification\Dto;

use App\Verification\Enum\VerificationAnswer;

final readonly class RespondRequest
{
    public function __construct(public VerificationAnswer $answer)
    {
    }
}
