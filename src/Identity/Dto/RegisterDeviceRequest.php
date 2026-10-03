<?php

declare(strict_types=1);

namespace App\Identity\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterDeviceRequest
{
    public function __construct(
        #[Assert\Choice(['ios', 'android', 'web', 'simulator'])]
        public ?string $platform = null,
        #[Assert\Length(max: 32)]
        public ?string $appVersion = null,
        #[Assert\Length(max: 4096)]
        public ?string $pushToken = null,
    ) {
    }
}
