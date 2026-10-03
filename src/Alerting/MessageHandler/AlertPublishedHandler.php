<?php

declare(strict_types=1);

namespace App\Alerting\MessageHandler;

use App\Alerting\Message\AlertPublished;
use App\Alerting\Repository\AlertRepository;
use App\Identity\Repository\DeviceRepository;
use App\Notification\PushMessage;
use App\Notification\PushSenderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class AlertPublishedHandler
{
    public function __construct(
        private AlertRepository $alerts,
        private DeviceRepository $devices,
        private PushSenderInterface $push,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(AlertPublished $event): void
    {
        $alert = $this->alerts->find(Uuid::fromString($event->alertId));
        if (null === $alert) {
            return;
        }

        $targets = $this->devices->findInsideGeometry($alert->getArea());
        $sent = $this->push->send($targets, PushMessage::alert($event->alertId, $alert->getTitle(), $alert->getBody()));

        $alert->setDeliveredCount(\count($targets));
        $this->em->flush();

        $this->logger->info('Alert {id}: {targets} devices in area, {sent} pushes sent', [
            'id' => $event->alertId, 'targets' => \count($targets), 'sent' => $sent,
        ]);
    }
}
