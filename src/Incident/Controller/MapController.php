<?php

declare(strict_types=1);

namespace App\Incident\Controller;

use App\Alerting\Repository\AlertRepository;
use App\Alerting\View\AlertView;
use App\Fuel\Repository\FuelStationRepository;
use App\Fuel\View\FuelStationView;
use App\Incident\Repository\IncidentRepository;
use App\Incident\View\IncidentPublicView;
use App\Shared\Api\ConditionalJsonResponse;
use App\Shared\Api\GeoJson;
use App\Shared\Geo\BoundingBox;
use App\Shared\Geo\Region;
use App\Shelter\Repository\ShelterRepository;
use App\Shelter\View\ShelterView;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * One call for the mobile map screen: incident areas + shelters + active alerts as a FeatureCollection.
 */
#[OA\Tag(name: 'Incidents')]
final class MapController
{
    public function __construct(
        private readonly IncidentRepository $incidents,
        private readonly ShelterRepository $shelters,
        private readonly FuelStationRepository $fuelStations,
        private readonly AlertRepository $alerts,
        private readonly Region $region,
    ) {
    }

    #[Route('/api/v1/map', name: 'api_map', methods: ['GET'])]
    #[OA\Get(summary: 'Everything visible on the citizen map inside a bbox, as GeoJSON')]
    #[OA\Parameter(name: 'bbox', in: 'query', description: 'minLng,minLat,maxLng,maxLat (defaults to the configured region: Poznań)', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'GeoJSON FeatureCollection; properties.kind in {incident, shelter, alert}. Sends an ETag.', content: new OA\JsonContent(ref: '#/components/schemas/MapFeatureCollection'))]
    #[OA\Response(response: 304, description: 'Not Modified (If-None-Match matched the ETag)')]
    #[OA\Response(response: 400, description: 'Malformed bbox (error.code: bad_request)')]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $bbox = $request->query->has('bbox') ? BoundingBox::fromString((string) $request->query->get('bbox')) : $this->region->boundingBox();
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $features = [
            ...array_map(IncidentPublicView::toFeature(...), $this->incidents->findOpenInBoundingBox($bbox)),
            ...array_map(ShelterView::toFeature(...), $this->shelters->findInBoundingBox($bbox)),
            ...array_map(FuelStationView::toFeature(...), $this->fuelStations->findInBoundingBox($bbox)),
            ...array_map(AlertView::toFeature(...), $this->alerts->findActiveInBoundingBox($bbox)),
        ];

        return ConditionalJsonResponse::create($request, GeoJson::collection($features));
    }
}
