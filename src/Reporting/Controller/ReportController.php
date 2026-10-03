<?php

declare(strict_types=1);

namespace App\Reporting\Controller;

use App\Identity\Entity\Device;
use App\Reporting\Dto\CreateReportRequest;
use App\Reporting\Entity\Report;
use App\Reporting\Service\ReportSubmitter;
use App\Reporting\View\ReportStatusView;
use App\Reporting\View\ReportTypeOptionView;
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
    #[OA\Response(response: 202, description: 'Accepted; clustering happens asynchronously', content: new OA\JsonContent(ref: '#/components/schemas/ReportAccepted'))]
    #[OA\Response(response: 429, description: 'More than 10 reports in 10 minutes from this device (error.code: too_many_requests, Retry-After header)')]
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

        return new JsonResponse(ReportStatusView::accepted($report), Response::HTTP_ACCEPTED);
    }

    #[Route('/types', name: 'api_report_types', methods: ['GET'])]
    #[OA\Get(summary: 'Report categories with labels (for the report screen)')]
    #[OA\Response(response: 200, description: 'List of types', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/ReportTypeOption')))]
    public function types(): JsonResponse
    {
        return new JsonResponse(ReportTypeOptionView::all());
    }

    #[Route('/{id}', name: 'api_report_show', methods: ['GET'])]
    #[OA\Get(summary: 'Status of one of my reports (which incident it joined)')]
    #[OA\Response(response: 200, description: 'Report status', content: new OA\JsonContent(ref: '#/components/schemas/ReportStatusView'))]
    public function show(#[CurrentUser] Device $device, Report $report): JsonResponse
    {
        if ($report->getDevice()->getId()->toRfc4122() !== $device->getId()->toRfc4122()) {
            throw $this->createNotFoundException();
        }

        return new JsonResponse(ReportStatusView::toArray($report));
    }
}
