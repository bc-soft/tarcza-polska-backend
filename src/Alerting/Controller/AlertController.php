<?php

declare(strict_types=1);

namespace App\Alerting\Controller;

use App\Alerting\Entity\Alert;
use App\Alerting\Repository\AlertRepository;
use App\Alerting\View\AlertView;
use App\Shared\Api\ConditionalJsonResponse;
use App\Shared\Geo\Point;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/alerts')]
#[OA\Tag(name: 'Alerts')]
final class AlertController
{
    public function __construct(private readonly AlertRepository $alerts)
    {
    }

    #[Route('', name: 'api_alert_list', methods: ['GET'])]
    #[OA\Get(summary: 'Active alerts covering my position')]
    #[OA\Parameter(name: 'lat', in: 'query', required: true, schema: new OA\Schema(type: 'number'))]
    #[OA\Parameter(name: 'lng', in: 'query', required: true, schema: new OA\Schema(type: 'number'))]
    #[OA\Response(response: 200, description: 'Alerts')]
    public function list(Request $request): JsonResponse
    {
        if (!$request->query->has('lat') || !$request->query->has('lng')) {
            throw new BadRequestHttpException('lat and lng are required');
        }
        $point = new Point((float) $request->query->get('lat'), (float) $request->query->get('lng'));

        return ConditionalJsonResponse::create($request, array_map(static fn (Alert $a) => AlertView::toArray($a, false), $this->alerts->findActiveContaining($point)));
    }

    #[Route('/{id}', name: 'api_alert_show', methods: ['GET'])]
    #[OA\Get(summary: 'Alert details (opened from a push)')]
    #[OA\Response(response: 200, description: 'Alert')]
    public function show(Alert $alert): JsonResponse
    {
        return new JsonResponse(AlertView::toArray($alert));
    }
}
