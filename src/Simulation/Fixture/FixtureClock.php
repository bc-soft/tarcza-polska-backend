<?php

declare(strict_types=1);

namespace App\Simulation\Fixture;

use DateInterval;
use DateTimeImmutable;
use ReflectionProperty;

/**
 * Entities stamp "now" in their constructors. Fixtures need incidents from yesterday and reports from an hour
 * ago, so this helper rewrites those private timestamps. Fixtures only; never used by production code.
 */
final class FixtureClock
{
    public function __construct(private readonly DateTimeImmutable $now = new DateTimeImmutable())
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function ago(string $interval): DateTimeImmutable
    {
        return $this->now->sub(DateInterval::createFromDateString($interval));
    }

    /** @param array<string, DateTimeImmutable|null> $timestamps property name => value */
    public function backdate(object $entity, array $timestamps): void
    {
        foreach ($timestamps as $property => $value) {
            $ref = new ReflectionProperty($entity, $property);
            $ref->setValue($entity, $value);
        }
    }
}
