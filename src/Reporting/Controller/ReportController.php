<?php

declare(strict_types=1);

namespace App\Reporting\Controller;

use App\Identity\Entity\Device;
use App\Reporting\Dto\CreateReportRequest;
use App\Reporting\Entity\Report;
use App\Reporting\Enum\ReportType;
use App\Reporting\Service\ReportSubmitter;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/reports')]
#[OA\Tag(name: 'Reports')]
final class ReportController extends AbstractController
{
    #[Route('', name: 'api_report_create', methods: ['POST'])]
    #[OA\Post(summary: 'Submit a report (type -> location -> optional description)')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: CreateReportRequest::class)))]
    #[OA\Response(response: 202, description: 'Accepted; clustering happens asynchronously')]
    #[OA\Response(response: 429, description: 'Too many reports from this device')]
    public function create(
        #[CurrentUser]
        Device $device,
        #[MapRequestPayload]
        CreateReportRequest $request,
        ReportSubmitter $submitter,
        RateLimiterFactory $reportSubmissionLimiter,
    ): JsonResponse {
        $reportSubmissionLimiter->create($device->getUserIdentifier())->consume()->ensureAccepted();

        $report = $submitter->submit($device, $request);

        return new JsonResponse([
            'reportId' => $report->getId()->toRfc4122(),
            'h3Cell' => $report->getH3Cell(),
            'createdAt' => $report->getCreatedAt()->format(\DATE_ATOM),
        ], Response::HTTP_ACCEPTED);
    }

    #[Route('/types', name: 'api_report_types', methods: ['GET'])]
    #[OA\Get(summary: 'Report categories with labels (for the report screen)')]
    #[OA\Response(response: 200, description: 'List of types')]
    public function types(): JsonResponse
    {
        return new JsonResponse(array_map(
            static fn (ReportType $t) => ['value' => $t->value, 'label' => $t->label()],
            ReportType::cases(),
        ));
    }

    #[Route('/{id}', name: 'api_report_show', methods: ['GET'])]
    #[OA\Get(summary: 'Status of one of my reports (which incident it joined)')]
    #[OA\Response(response: 200, description: 'Report')]
    public function show(#[CurrentUser] Device $device, Report $report): JsonResponse
    {
        if ($report->getDevice()->getId()->toRfc4122() !== $device->getId()->toRfc4122()) {
            throw $this->createNotFoundException();
        }

        $incident = $report->getIncident();

        return new JsonResponse([
            'reportId' => $report->getId()->toRfc4122(),
            'type' => $report->getType()->value,
            'typeLabel' => $report->getType()->label(),
            'createdAt' => $report->getCreatedAt()->format(\DATE_ATOM),
            'incident' => null === $incident ? null : [
                'id' => $incident->getId()->toRfc4122(),
                'type' => $incident->getType()->value,
                'typeLabel' => $incident->getType()->label(),
                'status' => $incident->getStatus()->value,
                'statusLabel' => $incident->getStatus()->label(),
                'confidenceLevel' => $incident->getConfidenceLevel()->value,
                'confidenceLabel' => $incident->getConfidenceLevel()->label(),
                'confidenceScore' => round($incident->getConfidenceScore(), 2),
            ],
        ]);
    }
}
