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
                ->withData($message->data)
                ->withAndroidConfig(AndroidConfig::fromArray(['priority' => $message->highPriority ? 'high' : 'normal']))
                ->withApnsConfig(ApnsConfig::fromArray(self::apns($message)));

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

    /**
     * APNs headers / aps payload. Urgent messages (verification questions live 90 s) go with
     * apns-priority 10 and interruption-level time-sensitive; reminders with priority 5.
     *
     * @return array<string, mixed>
     */
    private static function apns(PushMessage $message): array
    {
        $aps = ['sound' => 'default'];
        if ($message->timeSensitive) {
            $aps['interruption-level'] = 'time-sensitive';
        }

        return [
            'headers' => [
                'apns-push-type' => 'alert',
                'apns-priority' => $message->highPriority ? '10' : '5',
            ],
            'payload' => ['aps' => $aps],
        ];
    }
}
