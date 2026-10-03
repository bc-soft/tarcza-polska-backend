<?php

declare(strict_types=1);

namespace App\Verification\Service;

use App\Incident\Message\IncidentUpdated;
use App\Incident\Service\AreaCalculator;
use App\Shared\Geo\H3;
use App\Verification\Entity\VerificationRequest;
use App\Verification\Enum\VerificationAnswer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Records an answer, updates the cell, recomputes the area and notifies the rest of the system.
 */
final readonly class VerificationResponder
{
    public function __construct(
        private AreaCalculator $areaCalculator,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private H3 $h3,
    ) {
    }

    public function respond(VerificationRequest $request, VerificationAnswer $answer): void
    {
        $normalised = $request->answerWith($answer);
        $incident = $request->getIncident();

        $cell = $incident->cell($request->getH3Cell(), $this->h3->distance($incident->getCenterCell(), $request->getH3Cell()));
        $cell->addAnswer($normalised);

        $this->areaCalculator->recompute($incident);
        $incident->touch();
        $request->getDevice()->touch();
        $this->em->flush();

        $this->bus->dispatch(new IncidentUpdated($incident->getId()->toRfc4122(), 'verification_response'));
    }
}
