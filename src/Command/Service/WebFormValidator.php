<?php

declare(strict_types=1);

namespace App\Command\Service;

use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Turns constraint violations of a form-mapped DTO into flash-friendly messages. */
final readonly class WebFormValidator
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    /** @return list<string> empty when the DTO is valid */
    public function errors(object $dto): array
    {
        $out = [];
        /** @var ConstraintViolationInterface $violation */
        foreach ($this->validator->validate($dto) as $violation) {
            $out[] = \sprintf('%s: %s', $violation->getPropertyPath(), (string) $violation->getMessage());
        }

        return $out;
    }
}
