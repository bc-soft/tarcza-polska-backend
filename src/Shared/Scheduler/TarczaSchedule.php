<?php

declare(strict_types=1);

namespace App\Shared\Scheduler;

use App\Simulation\Message\SimulatedCrowdTick;
use App\Verification\Message\VerificationTick;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Periodic heartbeat of the platform. Consumed by: messenger:consume scheduler_tarcza.
 */
#[AsSchedule('tarcza')]
final class TarczaSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->with(
                // Close expired verification waves, update cell states, plan the next ring.
                RecurringMessage::every('20 seconds', new VerificationTick()),
                // Simulated citizens answer their pending verification requests (demo only, no-op otherwise).
                RecurringMessage::every('10 seconds', new SimulatedCrowdTick()),
            )
            ->lock($this->lockFactory->createLock('tarcza-schedule'))
            ->stateful($this->cache);
    }
}
