<?php

declare(strict_types=1);

namespace App\Verification\Service;

use App\Fuel\Enum\FuelType;
use App\Incident\Message\IncidentUpdated;
use App\Incident\Service\AreaCalculator;
use App\Shared\Geo\H3;
use App\Shared\Poi\PoiRegistry;
use App\Verification\Entity\VerificationRequest;
use App\Verification\Enum\VerificationAnswer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Records an answer and routes it:
 *  - area question  -> the cell the device stood in (area grows / shrinks),
 *  - point question -> the object's own status (fuel availability, shelter accessibility); only answers about
 *                      the incident's object also count towards the incident's confidence.
 */
final readonly class VerificationResponder
{
    public function __construct(
        private AreaCalculator $areaCalculator,
        private PoiRegistry $pois,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private H3 $h3,
    ) {
    }

    public function respond(VerificationRequest $request, VerificationAnswer $answer): void
    {
        $normalised = $request->answerWith($answer);
        $incident = $request->getIncident();
        $poiKind = $request->getPoiKind();
        $poiId = $request->getPoiId();

        if (null !== $poiKind && null !== $poiId) {
            if ('unknown' !== $normalised) {
                $this->pois->updater($poiKind)->applyAnswer(
                    $poiId,
                    'yes' === $normalised,
                    array_map(static fn (FuelType $t) => $t->value, $incident->getFuelTypes()),
                );
            }
            if ($request->isAboutIncidentPoi()) {
                $incident->cell($incident->getCenterCell(), 0)->addAnswer($normalised);
            }
        } else {
            $cell = $incident->cell($request->getH3Cell(), $this->h3->distance($incident->getCenterCell(), $request->getH3Cell()));
            $cell->addAnswer($normalised);
        }

        $this->areaCalculator->recompute($incident);
        $incident->touch();
        $request->getDevice()->touch();
        $this->em->flush();

        $this->bus->dispatch(new IncidentUpdated($incident->getId()->toRfc4122(), 'verification_response'));
    }
}
