<?php

declare(strict_types=1);

namespace App\Command\Controller\Api;

use App\Audit\Service\AuditLogger;
use App\Command\Dto\AddExternalSourceRequest;
use App\Command\View\IncidentCommandView;
use App\Identity\Entity\Operator;
use App\Incident\Entity\Incident;
use App\Incident\Message\ResolveIncident;
use App\Incident\Repository\IncidentRepository;
use App\Intelligence\Entity\ExternalSource;
use App\Intelligence\Repository\ExternalSourceRepository;
use App\Intelligence\Service\ExternalSourceRecorder;
use App\Verification\Repository\VerificationRequestRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/command/incidents')]
#[OA\Tag(name: 'Command')]
final class CommandIncidentController
{
    public function __construct(
        private readonly IncidentRepository $incidents,
        private readonly ExternalSourceRepository $sources,
        private readonly VerificationRequestRepository $verifications,
        private readonly IncidentCommandView $view,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('', name: 'api_command_incident_list', methods: ['GET'])]
    #[OA\Get(summary: 'Incident feed for the Command Center (open first)')]
    #[OA\Parameter(name: 'all', in: 'query', description: '1 = include resolved', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Incident summaries')]
    public function list(Request $request): JsonResponse
    {
        $items = $request->query->getBoolean('all') ? $this->incidents->findRecent() : $this->incidents->findOpen();

        return new JsonResponse(array_map($this->view->summary(...), $items));
    }

    #[Route('/{id}', name: 'api_command_incident_show', methods: ['GET'])]
    #[OA\Get(summary: 'Full incident detail: raw reports, cells, sources, confidence breakdown (audit-logged)')]
    #[OA\Response(response: 200, description: 'Incident detail')]
    public function show(#[CurrentUser] Operator $operator, Incident $incident): JsonResponse
    {
        $this->audit->log($operator, AuditLogger::VIEW_SENSITIVE, 'incident', $incident->getId()->toRfc4122());

        return new JsonResponse($this->view->detail(
            $incident,
            $this->sources->findByIncident($incident),
            $this->verifications->statsForIncident($incident),
        ));
    }

    #[Route('/{id}/sources', name: 'api_command_incident_add_source', methods: ['POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    #[OA\Post(summary: 'Attach an external/official source manually (raises confidence deterministically)')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: AddExternalSourceRequest::class)))]
    #[OA\Response(response: 201, description: 'Source stored')]
    public function addSource(
        #[CurrentUser]
        Operator $operator,
        Incident $incident,
        #[MapRequestPayload]
        AddExternalSourceRequest $request,
        ExternalSourceRecorder $recorder,
    ): JsonResponse {
        $source = $recorder->record($incident, $request->url, $request->title, $request->kind, $request->credibility, 'operator', $request->publisher, $request->excerpt);
        $this->audit->log($operator, AuditLogger::ADD_SOURCE, 'incident', $incident->getId()->toRfc4122(), ['url' => $request->url]);

        return new JsonResponse($source->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}/resolve', name: 'api_command_incident_resolve', methods: ['POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    #[OA\Post(summary: 'Close an incident')]
    #[OA\Response(response: 202, description: 'Resolution queued')]
    public function resolve(#[CurrentUser] Operator $operator, Incident $incident, MessageBusInterface $bus): Response
    {
        $bus->dispatch(new ResolveIncident($incident->getId()->toRfc4122(), $operator->getUserIdentifier()));
        $this->audit->log($operator, AuditLogger::RESOLVE_INCIDENT, 'incident', $incident->getId()->toRfc4122());

        return new Response(status: Response::HTTP_ACCEPTED);
    }

    #[Route('/{id}/sources', name: 'api_command_incident_sources', methods: ['GET'])]
    #[OA\Get(summary: 'External sources attached to the incident')]
    #[OA\Response(response: 200, description: 'Sources')]
    public function sources(Incident $incident): JsonResponse
    {
        return new JsonResponse(array_map(static fn (ExternalSource $s) => $s->toArray(), $this->sources->findByIncident($incident)));
    }
}
