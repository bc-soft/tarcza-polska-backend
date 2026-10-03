<?php

declare(strict_types=1);

namespace App\Identity\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdatePushTokenRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 4096)]
        public string $pushToken,
    ) {
    }
}
