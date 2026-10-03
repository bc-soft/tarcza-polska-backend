<?php

declare(strict_types=1);

namespace App\Intelligence\Service;

use App\Incident\Entity\Incident;
use App\Incident\Message\IncidentUpdated;
use App\Intelligence\Entity\ExternalSource;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/** Shared by the AI handler, the operator UI and the demo simulator. */
final readonly class ExternalSourceRecorder
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
    ) {
    }

    public function record(
        Incident $incident,
        string $url,
        string $title,
        string $kind,
        float $credibility,
        string $foundBy,
        ?string $publisher = null,
        ?string $excerpt = null,
        ?DateTimeImmutable $publishedAt = null,
        bool $notify = true,
    ): ExternalSource {
        $source = new ExternalSource($incident, $url, $title, $kind, $credibility, $foundBy);
        $source->setPublisher($publisher);
        $source->setExcerpt($excerpt);
        $source->setPublishedAt($publishedAt);
        $this->em->persist($source);
        $incident->touch();
        $this->em->flush();

        if ($notify) {
            $this->bus->dispatch(new IncidentUpdated($incident->getId()->toRfc4122(), 'external_source'));
        }

        return $source;
    }
}
