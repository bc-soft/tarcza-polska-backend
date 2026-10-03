<?php

declare(strict_types=1);

namespace App\Guidance\Controller;

use App\Alerting\Repository\AlertRepository;
use App\Guidance\Model\OfflineBundle;
use App\Guidance\Service\ProcedureCatalog;
use App\Guidance\View\OfflineBundleView;
use App\Incident\Repository\IncidentRepository;
use App\Shared\Api\ApiProblemException;
use App\Shared\Api\ConditionalJsonResponse;
use App\Shared\Geo\BoundingBox;
use App\Shared\Geo\Point;
use App\Shelter\Repository\ShelterRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * One call the app makes while it still has network, so that shelters, procedures, current alerts and the last
 * known incident picture around the user survive a loss of connectivity ("offline / degraded mode").
 */
#[OA\Tag(name: 'Guidance')]
final class OfflineBundleController
{
    private const int DEFAULT_RADIUS_M = 15000;
    private const int MAX_RADIUS_M = 50000;
    private const int MAX_SHELTERS = 50;
    private const int VALID_HOURS = 24;

    public function __construct(
        private readonly ShelterRepository $shelters,
        private readonly AlertRepository $alerts,
        private readonly IncidentRepository $incidents,
        private readonly ProcedureCatalog $procedures,
    ) {
    }

    #[Route('/api/v1/offline-bundle', name: 'api_offline_bundle', methods: ['GET'])]
    #[OA\Get(summary: 'Everything to cache for offline mode around a position: shelters, active alerts, open incidents, procedures')]
    #[OA\Parameter(name: 'lat', in: 'query', required: true, schema: new OA\Schema(type: 'number'))]
    #[OA\Parameter(name: 'lng', in: 'query', required: true, schema: new OA\Schema(type: 'number'))]
    #[OA\Parameter(name: 'radiusMeters', in: 'query', description: 'Default 15000, max 50000', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Offline bundle (supports ETag / 304; refresh when validUntil passes or on foreground)', content: new OA\JsonContent(ref: '#/components/schemas/OfflineBundle'))]
    #[OA\Response(response: 400, description: 'Missing or invalid lat/lng (error.code: bad_request)')]
    public function __invoke(Request $request): JsonResponse
    {
        if (!$request->query->has('lat') || !$request->query->has('lng')) {
            throw new ApiProblemException(400, 'bad_request', 'lat and lng are required');
        }
        try {
            $center = new Point((float) $request->query->get('lat'), (float) $request->query->get('lng'));
        } catch (InvalidArgumentException $e) {
            throw new ApiProblemException(400, 'bad_request', $e->getMessage());
        }
        $radius = max(1000, min(self::MAX_RADIUS_M, $request->query->getInt('radiusMeters', self::DEFAULT_RADIUS_M)));
        $bbox = BoundingBox::around($center, $radius);
        $now = new DateTimeImmutable();

        $bundle = new OfflineBundle(
            center: $center,
            radiusMeters: $radius,
            generatedAt: $now,
            validUntil: $now->modify(\sprintf('+%d hours', self::VALID_HOURS)),
            shelters: $this->shelters->findWithinRadius($center, $radius, self::MAX_SHELTERS),
            alerts: $this->alerts->findActiveInBoundingBox($bbox),
            incidents: $this->incidents->findOpenInBoundingBox($bbox),
            procedures: $this->procedures->all(),
        );

        return ConditionalJsonResponse::create($request, OfflineBundleView::toArray($bundle));
    }
}
