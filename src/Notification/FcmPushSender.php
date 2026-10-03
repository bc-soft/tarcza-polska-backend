<?php

declare(strict_types=1);

namespace App\Notification;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Firebase Cloud Messaging delivery (Android + iOS through APNs).
 */
final readonly class FcmPushSender implements PushSenderInterface
{
    public function __construct(
        private Messaging $messaging,
        private LoggerInterface $logger,
    ) {
    }

    public function send(iterable $devices, PushMessage $message): int
    {
        $count = 0;
        foreach ($devices as $device) {
            if (!$device->canReceivePush()) {
                continue;
            }
            $token = (string) $device->getPushToken();
            if ('' === $token) {
                continue;
            }

            $cloudMessage = CloudMessage::new()
                ->toToken($token)
                ->withNotification(Notification::create($message->title, $message->body))
                ->withData($message->data);

            if ($message->highPriority) {
                $cloudMessage = $cloudMessage
                    ->withAndroidConfig(AndroidConfig::fromArray(['priority' => 'high']))
                    ->withApnsConfig(ApnsConfig::fromArray(['headers' => ['apns-priority' => '10'], 'payload' => ['aps' => ['sound' => 'default']]]));
            }

            try {
                $this->messaging->send($cloudMessage);
                ++$count;
            } catch (Throwable $e) {
                $this->logger->warning('FCM send failed for device {device}: {error}', [
                    'device' => $device->getUserIdentifier(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }
}
