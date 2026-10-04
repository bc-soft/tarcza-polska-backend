<?php

declare(strict_types=1);

namespace App\Incident\Controller;

use App\Incident\Entity\Incident;
use App\Incident\Repository\IncidentRepository;
use App\Incident\Service\IncidentTimeline;
use App\Incident\View\IncidentPublicView;
use App\Incident\View\IncidentTimelineView;
use App\Shared\Api\ConditionalJsonResponse;
use App\Shared\Geo\Point;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/incidents')]
#[OA\Tag(name: 'Incidents')]
final class IncidentController
{
    public function __construct(
        private readonly IncidentRepository $incidents,
        private readonly IncidentTimeline $timeline,
    ) {
    }

    #[Route('', name: 'api_incident_list', methods: ['GET'])]
    #[OA\Get(summary: 'Open incidents (optionally only those containing my position)')]
    #[OA\Parameter(name: 'lat', in: 'query', schema: new OA\Schema(type: 'number'))]
    #[OA\Parameter(name: 'lng', in: 'query', schema: new OA\Schema(type: 'number'))]
    #[OA\Response(response: 200, description: 'List of public incident views', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/IncidentView')))]
    public function list(Request $request): JsonResponse
    {
        if ($request->query->has('lat') && $request->query->has('lng')) {
            try {
                $point = new Point((float) $request->query->get('lat'), (float) $request->query->get('lng'));
            } catch (InvalidArgumentException $e) {
                throw new BadRequestHttpException($e->getMessage(), $e);
            }
            $items = $this->incidents->findOpenContaining($point);
        } else {
            $items = $this->incidents->findOpen();
        }

        return new JsonResponse(array_map(IncidentPublicView::toArray(...), $items));
    }

    #[Route('/{id}', name: 'api_incident_show', methods: ['GET'])]
    #[OA\Get(summary: 'Public view of an incident (aggregated, no raw report positions)')]
    #[OA\Response(response: 200, description: 'Incident', content: new OA\JsonContent(ref: '#/components/schemas/IncidentView'))]
    public function show(Incident $incident): JsonResponse
    {
        return new JsonResponse(IncidentPublicView::toArray($incident));
    }

    #[Route('/{id}/timeline', name: 'api_incident_timeline', methods: ['GET'])]
    #[OA\Get(summary: 'History of an incident: detection, verification waves, area changes, confidence changes, confirmations, alerts')]
    #[OA\Response(response: 200, description: 'Timeline entries, oldest first (supports ETag / 304)', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/IncidentTimelineEntry')))]
    public function timeline(Incident $incident, Request $request): JsonResponse
    {
        return ConditionalJsonResponse::create($request, IncidentTimelineView::list($this->timeline->entries($incident, publicOnly: true), public: true));
    }
}
