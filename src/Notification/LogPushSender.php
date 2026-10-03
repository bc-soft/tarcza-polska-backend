<?php

declare(strict_types=1);

namespace App\Notification;

use Psr\Log\LoggerInterface;

/** Used when FIREBASE_CREDENTIALS is empty (local dev, CI). */
final readonly class LogPushSender implements PushSenderInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function send(iterable $devices, PushMessage $message): int
    {
        $count = 0;
        foreach ($devices as $device) {
            if (!$device->canReceivePush()) {
                continue;
            }
            ++$count;
            $this->logger->info('[push:noop] {title} -> {device}', [
                'title' => $message->title,
                'body' => $message->body,
                'data' => $message->data,
                'device' => $device->getUserIdentifier(),
            ]);
        }

        return $count;
    }
}
