<?php

declare(strict_types=1);

namespace App\Identity\Service;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Local-time window in which non-urgent pushes (location reminders) are not sent, e.g. 21:00-08:00.
 * Verification questions and alerts are urgent and ignore it.
 */
final readonly class QuietHours
{
    private DateTimeZone $timezone;

    public function __construct(
        private int $startHour = 21,
        private int $endHour = 8,
        string $timezone = 'Europe/Warsaw',
    ) {
        if ($startHour < 0 || $startHour > 23 || $endHour < 0 || $endHour > 23) {
            throw new InvalidArgumentException('Quiet hours must be within 0..23');
        }
        $this->timezone = new DateTimeZone($timezone);
    }

    public function contains(DateTimeImmutable $moment): bool
    {
        $hour = (int) $moment->setTimezone($this->timezone)->format('G');

        if ($this->startHour === $this->endHour) {
            return false;
        }
        if ($this->startHour < $this->endHour) {
            return $hour >= $this->startHour && $hour < $this->endHour;
        }

        // Window crosses midnight (21 -> 8).
        return $hour >= $this->startHour || $hour < $this->endHour;
    }
}
