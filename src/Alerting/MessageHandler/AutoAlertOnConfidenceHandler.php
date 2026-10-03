<?php

declare(strict_types=1);

namespace App\Alerting\MessageHandler;

use App\Alerting\Repository\AlertRepository;
use App\Alerting\Service\AlertPublisher;
use App\Alerting\Service\AutoAlertComposer;
use App\Confidence\Message\IncidentConfidenceUpdated;
use App\Incident\Enum\ConfidenceLevel;
use App\Incident\Repository\IncidentRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Automatic geographic push (post-MVP): the first time an incident reaches AUTO_ALERT_LEVEL, everyone inside
 * its area gets a system alert. Operators can still send their own, richer message afterwards.
 */
#[AsMessageHandler]
final readonly class AutoAlertOnConfidenceHandler
{
    public function __construct(
        private IncidentRepository $incidents,
        private AlertRepository $alerts,
        private AlertPublisher $publisher,
        private AutoAlertComposer $composer,
        private LoggerInterface $logger,
        private string $autoAlertLevel,
    ) {
    }

    public function __invoke(IncidentConfidenceUpdated $event): void
    {
        $threshold = ConfidenceLevel::tryFrom(strtolower($this->autoAlertLevel));
        if (null === $threshold || !$event->levelChanged()) {
            return; // AUTO_ALERT_LEVEL=off (or any non-level value) disables the feature
        }
        $current = ConfidenceLevel::from($event->currentLevel);
        $previous = ConfidenceLevel::from($event->previousLevel);
        if (!$current->atLeast($threshold) || $previous->atLeast($threshold)) {
            return; // only on the upward crossing
        }

        $incident = $this->incidents->find(Uuid::fromString($event->incidentId));
        if (null === $incident || !$incident->getStatus()->isOpen() || null === $incident->getArea()) {
            return;
        }
        if ($this->alerts->existsForIncidentBy($incident, AutoAlertComposer::SYSTEM_AUTHOR)) {
            return;
        }

        $alert = $this->publisher->publish($this->composer->compose($incident), AutoAlertComposer::SYSTEM_AUTHOR);
        $this->logger->info('Incident {id} reached {level}: automatic alert {alert} published', [
            'id' => $event->incidentId, 'level' => $current->value, 'alert' => $alert->getId()->toRfc4122(),
        ]);
    }
}
