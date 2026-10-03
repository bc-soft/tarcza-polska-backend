<?php

declare(strict_types=1);

namespace App\Identity\Message;

/** Scheduler heartbeat: remind devices with a stale position to open the app (push location_refresh). */
final readonly class LocationRefreshTick
{
}
