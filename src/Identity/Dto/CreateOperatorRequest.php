<?php

declare(strict_types=1);

namespace App\Identity\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateOperatorRequest
{
    /** @param list<string> $roles */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public string $email,
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public string $displayName,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 200)]
        public string $password,
        #[Assert\All([new Assert\Choice(['ROLE_ANALYST', 'ROLE_OPERATOR', 'ROLE_ADMIN'])])]
        public array $roles = ['ROLE_ANALYST'],
        #[Assert\Length(max: 120)]
        public ?string $organisation = null,
    ) {
    }
}
