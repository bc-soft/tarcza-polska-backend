<?php

declare(strict_types=1);

namespace App\Guidance\Controller;

use App\Guidance\Service\ProcedureCatalog;
use App\Guidance\View\ProcedureView;
use App\Reporting\Enum\ReportType;
use App\Shared\Api\ApiProblemException;
use App\Shared\Api\ConditionalJsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Guidance')]
final class ProcedureController
{
    public function __construct(private readonly ProcedureCatalog $catalog)
    {
    }

    #[Route('/api/v1/procedures', name: 'api_procedures', methods: ['GET'])]
    #[OA\Get(summary: 'Safety procedures (Polish checklists) for offline use; optionally filtered by incident type')]
    #[OA\Parameter(name: 'type', in: 'query', description: 'Report type; returns procedures for that type plus the general ones', schema: new OA\Schema(ref: '#/components/schemas/ReportType'))]
    #[OA\Response(response: 200, description: 'Procedures, highest priority first (supports ETag / 304)', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Procedure')))]
    #[OA\Response(response: 400, description: 'Unknown type (error.code: bad_request)')]
    public function __invoke(Request $request): JsonResponse
    {
        $type = $request->query->getString('type');
        if ('' === $type) {
            return ConditionalJsonResponse::create($request, ProcedureView::list($this->catalog->all()));
        }

        $reportType = ReportType::tryFrom($type) ?? throw new ApiProblemException(400, 'bad_request', \sprintf('Unknown type "%s"', $type));

        return ConditionalJsonResponse::create($request, ProcedureView::list($this->catalog->forType($reportType)));
    }
}
