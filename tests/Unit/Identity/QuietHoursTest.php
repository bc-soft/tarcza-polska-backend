<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Service\QuietHours;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuietHoursTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function moments(): iterable
    {
        // Instants in UTC; Europe/Warsaw is UTC+2 in summer (CEST).
        yield 'evening 21:00 local' => ['2026-07-01T19:00:00+00:00', true];
        yield 'night 03:30 local' => ['2026-07-01T01:30:00+00:00', true];
        yield 'morning 07:59 local' => ['2026-07-01T05:59:00+00:00', true];
        yield 'morning 08:00 local' => ['2026-07-01T06:00:00+00:00', false];
        yield 'noon local' => ['2026-07-01T10:00:00+00:00', false];
        yield 'evening 20:59 local' => ['2026-07-01T18:59:00+00:00', false];
        // Winter (CET, UTC+1): 21:00 UTC is 22:00 local.
        yield 'winter 22:00 local' => ['2026-01-10T21:00:00+00:00', true];
    }

    #[DataProvider('moments')]
    public function testDefaultWindowCrossesMidnightInLocalTime(string $utc, bool $expected): void
    {
        self::assertSame($expected, new QuietHours()->contains(new DateTimeImmutable($utc)));
    }

    public function testDaytimeWindowDoesNotCrossMidnight(): void
    {
        $hours = new QuietHours(9, 17, 'UTC');

        self::assertTrue($hours->contains(new DateTimeImmutable('2026-07-01T12:00:00+00:00')));
        self::assertFalse($hours->contains(new DateTimeImmutable('2026-07-01T20:00:00+00:00')));
    }

    public function testEmptyWindowNeverMatches(): void
    {
        self::assertFalse(new QuietHours(8, 8, 'UTC')->contains(new DateTimeImmutable('2026-07-01T08:30:00+00:00')));
    }
}
