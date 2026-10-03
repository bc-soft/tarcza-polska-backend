<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Notification\PushMessage;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PushMessageTest extends TestCase
{
    public function testVerificationCarriesIdsAndExpiry(): void
    {
        $expires = new DateTimeImmutable('2026-07-01T12:01:30+02:00');
        $m = PushMessage::verification('req-1', 'Czy masz prąd?', $expires, ['incidentId' => 'inc-1', 'incidentType' => 'power_outage', 'type' => 'power_outage']);

        self::assertSame([
            'type' => 'verification',
            'verificationId' => 'req-1',
            'expiresAt' => '2026-07-01T12:01:30+02:00',
            'incidentId' => 'inc-1',
            'incidentType' => 'power_outage',
        ], $m->data, 'caller data must not override the routing type');
        self::assertTrue($m->highPriority);
        self::assertTrue($m->timeSensitive);
    }

    public function testLocationRefreshIsLowPriorityAndNotTimeSensitive(): void
    {
        $m = PushMessage::locationRefresh();

        self::assertSame(['type' => 'location_refresh'], $m->data);
        self::assertFalse($m->highPriority);
        self::assertFalse($m->timeSensitive);
    }
}
