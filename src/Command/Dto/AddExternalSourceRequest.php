<?php

declare(strict_types=1);

namespace App\Command\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddExternalSourceRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Url(requireTld: true)]
        public string $url,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $title,
        #[Assert\Choice(['official', 'infrastructure_operator', 'media', 'social', 'other'])]
        public string $kind = 'official',
        #[Assert\Range(min: 0, max: 1)]
        public float $credibility = 0.9,
        public ?string $publisher = null,
        public ?string $excerpt = null,
    ) {
    }
}
