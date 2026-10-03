<?php

declare(strict_types=1);

namespace App\Command\Service;

use App\Command\View\IncidentCommandView;
use App\Incident\Entity\Incident;
use App\Incident\View\IncidentPublicView;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

/**
 * Pushes incident changes to the Command Center (and any other SSE subscriber) through Mercure.
 * Topics: "incidents" (summary feed) and "incidents/{id}" (operator detail refresh trigger).
 */
final readonly class IncidentBroadcaster
{
    public function __construct(
        private HubInterface $hub,
        private IncidentCommandView $view,
        private LoggerInterface $logger,
    ) {
    }

    public function broadcast(Incident $incident, string $reason): void
    {
        $id = $incident->getId()->toRfc4122();

        try {
            $this->hub->publish(new Update(
                'incidents',
                json_encode(['event' => 'incident.updated', 'reason' => $reason, 'incident' => $this->view->summary($incident)], \JSON_THROW_ON_ERROR),
            ));
            $this->hub->publish(new Update(
                'incidents/'.$id,
                json_encode(['event' => 'incident.updated', 'reason' => $reason, 'incident' => IncidentPublicView::toArray($incident), 'cells' => $this->view->cellsCollection($incident)], \JSON_THROW_ON_ERROR),
            ));
        } catch (Throwable $e) {
            // Realtime is best-effort: never fail the pipeline because the hub is down.
            $this->logger->warning('Mercure publish failed: {error}', ['error' => $e->getMessage()]);
        }
    }
}
