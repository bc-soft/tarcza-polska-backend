<?php

declare(strict_types=1);

namespace App\Identity\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateOperatorRequest
{
    /** @param list<string> $roles */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public string $displayName,
        #[Assert\All([new Assert\Choice(['ROLE_ANALYST', 'ROLE_OPERATOR', 'ROLE_ADMIN'])])]
        public array $roles = ['ROLE_ANALYST'],
        #[Assert\Length(max: 120)]
        public ?string $organisation = null,
        /** Empty = keep the current password. */
        #[Assert\Length(min: 8, max: 200)]
        public ?string $newPassword = null,
    ) {
    }
}
