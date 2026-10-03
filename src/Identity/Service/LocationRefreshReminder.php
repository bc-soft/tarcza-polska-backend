<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Repository\DeviceRepository;
use App\Notification\PushMessage;
use App\Notification\PushSenderInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Pushes `location_refresh` to devices whose position is older than STALE_AFTER_HOURS, so the user
 * opens the app and the backend can target questions and alerts correctly again.
 *
 * Rules: opt-in flag on the device (default on), at most one reminder per day, never during quiet
 * hours, never to devices unseen for IGNORE_AFTER_DAYS. Devices that refresh from the background
 * are not stale by definition and therefore never match.
 */
final readonly class LocationRefreshReminder
{
    public const int STALE_AFTER_HOURS = 24;
    public const int MIN_INTERVAL_HOURS = 24;
    public const int IGNORE_AFTER_DAYS = 30;
    private const int BATCH = 500;

    public function __construct(
        private DeviceRepository $devices,
        private PushSenderInterface $push,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private QuietHours $quietHours,
        private LoggerInterface $logger,
    ) {
    }

    /** @return int number of reminders handed to the push provider */
    public function run(): int
    {
        $now = DateTimeImmutable::createFromInterface($this->clock->now());
        if ($this->quietHours->contains($now)) {
            return 0;
        }

        $candidates = $this->devices->findLocationRefreshCandidates(
            staleBefore: $now->modify(\sprintf('-%d hours', self::STALE_AFTER_HOURS)),
            notRemindedSince: $now->modify(\sprintf('-%d hours', self::MIN_INTERVAL_HOURS)),
            seenAfter: $now->modify(\sprintf('-%d days', self::IGNORE_AFTER_DAYS)),
            limit: self::BATCH,
        );
        if ([] === $candidates) {
            return 0;
        }

        foreach ($candidates as $device) {
            $device->markLocationRefreshSent($now);
        }
        $this->em->flush();

        $sent = $this->push->send($candidates, PushMessage::locationRefresh());
        $this->logger->info('location_refresh: {sent} reminders sent to {candidates} stale devices', [
            'sent' => $sent,
            'candidates' => \count($candidates),
        ]);

        return $sent;
    }
}
