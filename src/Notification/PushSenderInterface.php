<?php

declare(strict_types=1);

namespace App\Notification;

use App\Identity\Entity\Device;

interface PushSenderInterface
{
    /**
     * Sends to every device that can receive pushes; silently skips the others.
     *
     * @param iterable<Device> $devices
     *
     * @return int number of messages handed to the provider
     */
    public function send(iterable $devices, PushMessage $message): int;
}
