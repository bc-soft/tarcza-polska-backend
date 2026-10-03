<?php

declare(strict_types=1);

namespace App\Command\Controller\Api;

use App\Alerting\Dto\CreateAlertRequest;
use App\Alerting\Entity\Alert;
use App\Alerting\Repository\AlertRepository;
use App\Alerting\Service\AlertPublisher;
use App\Alerting\View\AlertView;
use App\Audit\Service\AuditLogger;
use App\Identity\Entity\Operator;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/command/alerts')]
#[OA\Tag(name: 'Command')]
final class CommandAlertController
{
    public function __construct(
        private readonly AlertRepository $alerts,
        private readonly AlertPublisher $publisher,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('', name: 'api_command_alert_list', methods: ['GET'])]
    #[OA\Get(summary: 'Recent alerts')]
    #[OA\Response(response: 200, description: 'Alerts')]
    public function list(): JsonResponse
    {
        return new JsonResponse(array_map(static fn (Alert $a) => AlertView::toArray($a, false) + ['deliveredCount' => $a->getDeliveredCount(), 'createdBy' => $a->getCreatedBy()], $this->alerts->findRecent()));
    }

    #[Route('', name: 'api_command_alert_create', methods: ['POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    #[OA\Post(summary: 'Publish an alert to an area (incident area or explicit GeoJSON)')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: CreateAlertRequest::class)))]
    #[OA\Response(response: 201, description: 'Alert created; delivery runs asynchronously')]
    public function create(#[CurrentUser] Operator $operator, #[MapRequestPayload] CreateAlertRequest $request): JsonResponse
    {
        $alert = $this->publisher->publish($request, $operator->getUserIdentifier());
        $this->audit->log($operator, AuditLogger::PUBLISH_ALERT, 'alert', $alert->getId()->toRfc4122(), ['incidentId' => $request->incidentId]);

        return new JsonResponse(AlertView::toArray($alert), Response::HTTP_CREATED);
    }
}
