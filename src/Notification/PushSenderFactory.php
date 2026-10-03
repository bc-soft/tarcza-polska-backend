<?php

declare(strict_types=1);

namespace App\Notification;

use Kreait\Firebase\Factory;
use Psr\Log\LoggerInterface;

final readonly class PushSenderFactory
{
    public function __construct(
        private LoggerInterface $logger,
        private string $firebaseCredentials,
    ) {
    }

    public function create(): PushSenderInterface
    {
        if ('' === $this->firebaseCredentials || !is_file($this->firebaseCredentials)) {
            $this->logger->notice('FIREBASE_CREDENTIALS not set, push notifications are logged only.');

            return new LogPushSender($this->logger);
        }

        $messaging = new Factory()->withServiceAccount($this->firebaseCredentials)->createMessaging();

        return new FcmPushSender($messaging, $this->logger);
    }
}
