<?php

declare(strict_types=1);

namespace App\Incident\Controller;

use App\Incident\Entity\Incident;
use App\Incident\Repository\IncidentRepository;
use App\Incident\View\IncidentPublicView;
use App\Shared\Geo\Point;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/incidents')]
#[OA\Tag(name: 'Incidents')]
final class IncidentController
{
    public function __construct(private readonly IncidentRepository $incidents)
    {
    }

    #[Route('', name: 'api_incident_list', methods: ['GET'])]
    #[OA\Get(summary: 'Open incidents (optionally only those containing my position)')]
    #[OA\Parameter(name: 'lat', in: 'query', schema: new OA\Schema(type: 'number'))]
    #[OA\Parameter(name: 'lng', in: 'query', schema: new OA\Schema(type: 'number'))]
    #[OA\Response(response: 200, description: 'List of public incident views')]
    public function list(Request $request): JsonResponse
    {
        if ($request->query->has('lat') && $request->query->has('lng')) {
            $point = new Point((float) $request->query->get('lat'), (float) $request->query->get('lng'));
            $items = $this->incidents->findOpenContaining($point);
        } else {
            $items = $this->incidents->findOpen();
        }

        return new JsonResponse(array_map(IncidentPublicView::toArray(...), $items));
    }

    #[Route('/{id}', name: 'api_incident_show', methods: ['GET'])]
    #[OA\Get(summary: 'Public view of an incident (aggregated, no raw report positions)')]
    #[OA\Response(response: 200, description: 'Incident')]
    public function show(Incident $incident): JsonResponse
    {
        return new JsonResponse(IncidentPublicView::toArray($incident));
    }
}
