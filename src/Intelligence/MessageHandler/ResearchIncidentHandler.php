<?php

declare(strict_types=1);

namespace App\Intelligence\MessageHandler;

use App\Incident\Message\IncidentUpdated;
use App\Incident\Repository\IncidentRepository;
use App\Intelligence\Message\ResearchIncident;
use App\Intelligence\Service\ExternalSourceRecorder;
use App\Intelligence\Service\PlaceNamer;
use App\Intelligence\Service\ResearcherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

#[AsMessageHandler]
final readonly class ResearchIncidentHandler
{
    public function __construct(
        private IncidentRepository $incidents,
        private ResearcherInterface $researcher,
        private PlaceNamer $placeNamer,
        private ExternalSourceRecorder $recorder,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ResearchIncident $command): void
    {
        $incident = $this->incidents->find(Uuid::fromString($command->incidentId));
        if (null === $incident || null !== $incident->getResearchedAt()) {
            return;
        }
        if (!$this->researcher->isEnabled()) {
            $this->logger->notice('ANTHROPIC_API_KEY not set, skipping research for incident {id}', ['id' => $command->incidentId]);
            $incident->recordResearch(null);
            $this->em->flush();

            return;
        }

        try {
            $result = $this->researcher->research($incident, $this->placeNamer->nameFor($incident->getCentroid()));
        } catch (Throwable $e) {
            $this->logger->error('Research failed for incident {id}: {error}', ['id' => $command->incidentId, 'error' => $e->getMessage()]);
            throw $e; // let Messenger retry
        }

        foreach ($result->confirming() as $finding) {
            if ('' === $finding->url) {
                continue;
            }
            $this->recorder->record(
                $incident,
                $finding->url,
                $finding->title,
                $finding->kind,
                $finding->credibility,
                'ai',
                $finding->publisher,
                $finding->excerpt,
                $finding->publishedAt,
                notify: false,
            );
        }

        $incident->recordResearch('' !== $result->summary ? $result->summary : null);
        $this->em->flush();

        $this->bus->dispatch(new IncidentUpdated($command->incidentId, 'research_completed'));
    }
}
