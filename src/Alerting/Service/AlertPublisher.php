<?php

declare(strict_types=1);

namespace App\Alerting\Service;

use App\Alerting\Dto\CreateAlertRequest;
use App\Alerting\Entity\Alert;
use App\Alerting\Message\AlertPublished;
use App\Incident\Repository\IncidentRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final readonly class AlertPublisher
{
    public function __construct(
        private IncidentRepository $incidents,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
    ) {
    }

    public function publish(CreateAlertRequest $request, string $operatorEmail): Alert
    {
        $incident = null;
        $area = $request->area;

        if (null !== $request->incidentId) {
            $incident = $this->incidents->find(Uuid::fromString($request->incidentId));
            if (null === $incident) {
                throw new UnprocessableEntityHttpException('Unknown incident');
            }
            $area ??= $incident->getArea();
        }
        if (null === $area) {
            throw new UnprocessableEntityHttpException('Alert needs an area: pass "area" (GeoJSON) or an incident that already has one');
        }

        $alert = new Alert(
            trim($request->title),
            trim($request->body),
            $request->severity,
            $area,
            $operatorEmail,
            new DateTimeImmutable(\sprintf('+%d minutes', $request->ttlMinutes)),
            $incident,
        );
        $this->em->persist($alert);
        $this->em->flush();

        $this->bus->dispatch(new AlertPublished($alert->getId()->toRfc4122()));

        return $alert;
    }
}
