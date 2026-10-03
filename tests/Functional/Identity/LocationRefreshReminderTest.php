<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Entity\Device;
use App\Identity\Repository\DeviceRepository;
use App\Identity\Service\LocationRefreshReminder;
use App\Identity\Service\QuietHours;
use App\Notification\PushMessage;
use App\Notification\PushSenderInterface;
use App\Shared\Geo\Point;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/** Needs the app_test database (make test-db). */
final class LocationRefreshReminderTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testRemindsStaleDevicesOncePerDayOutsideQuietHours(): void
    {
        $noon = new MockClock('2026-07-01T12:00:00+02:00');
        $stale = $this->device('fcm-stale', locationAgeHours: 30);
        $this->device('fcm-fresh', locationAgeHours: 2);
        $optedOut = $this->device('fcm-opted-out', locationAgeHours: 30);
        $optedOut->setLocationRefreshEnabled(false);
        $this->em->flush();

        $spy = new PushSpy();
        $reminder = $this->reminder($spy, $noon);

        self::assertSame(1, $reminder->run());
        self::assertSame([$stale->getId()->toRfc4122()], $spy->recipients);
        self::assertSame('location_refresh', $spy->lastMessage?->data['type']);
        self::assertNotNull($stale->getLastLocationRefreshAt());

        // Daily cap: the same tick an hour later sends nothing.
        $noon->modify('+1 hour');
        self::assertSame(0, $reminder->run());
    }

    public function testStaysSilentDuringQuietHours(): void
    {
        $this->device('fcm-night', locationAgeHours: 30);
        $spy = new PushSpy();

        self::assertSame(0, $this->reminder($spy, new MockClock('2026-07-01T23:30:00+02:00'))->run());
        self::assertSame([], $spy->recipients);
    }

    private function device(string $pushToken, int $locationAgeHours): Device
    {
        $device = new Device();
        $device->setPushToken($pushToken);
        $device->updateLocation(new Point(52.4, 16.9), '891e24aa0b3ffff');
        $this->em->persist($device);
        $this->em->flush();

        // The clock in the test is fixed in 2026-07; stamp the position relative to it.
        $updatedAt = new DateTimeImmutable('2026-07-01T12:00:00+02:00')->modify(\sprintf('-%d hours', $locationAgeHours));
        $this->em->getConnection()->executeStatement(
            'UPDATE device SET location_updated_at = :at, last_seen_at = :at WHERE id = :id',
            ['at' => $updatedAt->format('Y-m-d H:i:s'), 'id' => $device->getId()->toRfc4122()],
        );
        $this->em->refresh($device);

        return $device;
    }

    private function reminder(PushSenderInterface $push, MockClock $clock): LocationRefreshReminder
    {
        return new LocationRefreshReminder(
            self::getContainer()->get(DeviceRepository::class),
            $push,
            $this->em,
            $clock,
            new QuietHours(),
            new NullLogger(),
        );
    }
}

final class PushSpy implements PushSenderInterface
{
    /** @var list<string> */
    public array $recipients = [];
    public ?PushMessage $lastMessage = null;

    public function send(iterable $devices, PushMessage $message): int
    {
        $this->lastMessage = $message;
        $n = 0;
        foreach ($devices as $device) {
            if ($device->canReceivePush()) {
                $this->recipients[] = $device->getUserIdentifier();
                ++$n;
            }
        }

        return $n;
    }
}
