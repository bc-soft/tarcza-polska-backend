<?php

declare(strict_types=1);

namespace App\Notification;

use DateTimeImmutable;

/**
 * Platform-agnostic push payload. `data` is what the Flutter app routes on (e.g. type=verification, id=...).
 */
final readonly class PushMessage
{
    /** @param array<non-empty-string, string> $data */
    public function __construct(
        public string $title,
        public string $body,
        public array $data = [],
        public bool $highPriority = true,
        /** iOS interruption-level time-sensitive: breaks through Focus modes (needs the app capability). */
        public bool $timeSensitive = false,
    ) {
    }

    /** @param array<non-empty-string, string> $data */
    public static function verification(string $requestId, string $question, DateTimeImmutable $expiresAt, array $data = []): self
    {
        return new self(
            'Tarcza Polska - szybkie pytanie',
            $question,
            ['type' => 'verification', 'verificationId' => $requestId, 'expiresAt' => $expiresAt->format(\DATE_ATOM)] + $data,
            timeSensitive: true,
        );
    }

    public static function alert(string $alertId, string $title, string $body): self
    {
        return new self($title, $body, ['type' => 'alert', 'alertId' => $alertId], timeSensitive: true);
    }

    /** Gentle nudge to reopen the app so the device position gets refreshed. Normal priority. */
    public static function locationRefresh(): self
    {
        return new self(
            'Czy nadal jesteś w tej okolicy?',
            'Otwórz Tarczę, aby otrzymywać właściwe alerty.',
            ['type' => 'location_refresh'],
            highPriority: false,
        );
    }
}
