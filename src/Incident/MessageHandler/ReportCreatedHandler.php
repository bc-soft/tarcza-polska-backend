<?php

declare(strict_types=1);

namespace App\Incident\MessageHandler;

use App\Incident\Message\IncidentUpdated;
use App\Incident\Service\IncidentClusterer;
use App\Reporting\Message\ReportCreated;
use App\Reporting\Repository\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class ReportCreatedHandler
{
    public function __construct(
        private ReportRepository $reports,
        private IncidentClusterer $clusterer,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ReportCreated $event): void
    {
        $report = $this->reports->find(Uuid::fromString($event->reportId));
        if (null === $report) {
            $this->logger->warning('ReportCreated for unknown report {id}', ['id' => $event->reportId]);

            return;
        }
        if (null !== $report->getIncident()) {
            return; // idempotent on redelivery
        }

        $result = $this->clusterer->attach($report);
        $this->em->flush();

        $this->logger->info('Report {report} {verb} incident {incident}', [
            'report' => $event->reportId,
            'verb' => $result['created'] ? 'created' : 'joined',
            'incident' => $result['incident']->getId()->toRfc4122(),
        ]);

        $this->bus->dispatch(new IncidentUpdated($result['incident']->getId()->toRfc4122(), $result['created'] ? 'incident_created' : 'report_attached'));
    }
}
