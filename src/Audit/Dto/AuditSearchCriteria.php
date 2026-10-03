<?php

declare(strict_types=1);

namespace App\Audit\Dto;

final readonly class AuditSearchCriteria
{
    public function __construct(
        public ?string $actor = null,
        public ?string $action = null,
        public ?string $subjectType = null,
        public int $limit = 200,
    ) {
    }

    public static function fromStrings(?string $actor, ?string $action, ?string $subjectType, int $limit = 200): self
    {
        $clean = static fn (?string $v): ?string => null === $v || '' === trim($v) ? null : trim($v);

        return new self($clean($actor), $clean($action), $clean($subjectType), max(1, min(1000, $limit)));
    }
}
