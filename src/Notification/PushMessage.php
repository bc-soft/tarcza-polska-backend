<?php

declare(strict_types=1);

namespace App\Notification;

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
    ) {
    }

    /** @param array<non-empty-string, string> $data */
    public static function verification(string $requestId, string $question, array $data = []): self
    {
        return new self('Tarcza Polska - szybkie pytanie', $question, ['type' => 'verification', 'verificationId' => $requestId] + $data);
    }

    public static function alert(string $alertId, string $title, string $body): self
    {
        return new self($title, $body, ['type' => 'alert', 'alertId' => $alertId]);
    }
}
