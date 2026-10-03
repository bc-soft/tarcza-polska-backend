<?php

declare(strict_types=1);

namespace App\Intelligence\Service;

use App\Incident\Entity\Incident;
use App\Intelligence\Message\ResearchIncident;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Operator-triggered research: clears the "already researched" marker and queues a new run on the worker.
 * The handler itself stays the single place that talks to the provider.
 */
final readonly class ResearchRequester
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private ResearcherInterface $researcher,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->researcher->isEnabled();
    }

    public function providerName(): string
    {
        return $this->researcher->name();
    }

    public function requestAgain(Incident $incident): void
    {
        $incident->recordResearchReset();
        $this->em->flush();
        $this->bus->dispatch(new ResearchIncident($incident->getId()->toRfc4122()));
    }
}
