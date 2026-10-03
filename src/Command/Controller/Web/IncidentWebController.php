<?php

declare(strict_types=1);

namespace App\Command\Controller\Web;

use App\Alerting\Dto\CreateAlertRequest;
use App\Alerting\Service\AlertPublisher;
use App\Audit\Repository\AuditLogRepository;
use App\Audit\Service\AuditLogger;
use App\Command\Dto\AddExternalSourceRequest;
use App\Command\View\IncidentCommandView;
use App\Identity\Entity\Operator;
use App\Incident\Dto\IncidentSearchCriteria;
use App\Incident\Entity\Incident;
use App\Incident\Enum\ConfidenceLevel;
use App\Incident\Enum\IncidentResolution;
use App\Incident\Enum\IncidentStatus;
use App\Incident\Message\ResolveIncident;
use App\Incident\Repository\IncidentRepository;
use App\Intelligence\Repository\ExternalSourceRepository;
use App\Intelligence\Service\ExternalSourceRecorder;
use App\Intelligence\Service\ResearchRequester;
use App\Reporting\Enum\ReportType;
use App\Verification\Repository\VerificationRequestRepository;
use App\Verification\Repository\VerificationWaveRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Incident list, detail and operator actions (plain POST forms with CSRF). */
#[Route('/command/incidents')]
final class IncidentWebController extends AbstractCommandController
{
    public function __construct(
        private readonly IncidentRepository $incidents,
        private readonly IncidentCommandView $view,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('', name: 'command_incident_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $criteria = IncidentSearchCriteria::fromStrings(
            $request->query->getString('status') ?: null,
            $request->query->getString('type') ?: null,
            $request->query->getString('level') ?: null,
            $request->query->getBoolean('all'),
        );

        return $this->render('command/incident_list.html.twig', [
            'incidents' => array_map($this->view->summary(...), $this->incidents->search($criteria)),
            'criteria' => $criteria,
            'counts' => $this->incidents->countByStatus(),
            'types' => ReportType::cases(),
            'statuses' => IncidentStatus::cases(),
            'levels' => ConfidenceLevel::cases(),
        ]);
    }

    #[Route('/{id}', name: 'command_incident', methods: ['GET'])]
    public function show(
        #[CurrentUser]
        Operator $operator,
        Incident $incident,
        ExternalSourceRepository $sources,
        VerificationRequestRepository $requests,
        VerificationWaveRepository $waves,
        AuditLogRepository $auditLog,
        ResearchRequester $research,
    ): Response {
        $id = $incident->getId()->toRfc4122();
        $this->audit->log($operator, AuditLogger::VIEW_SENSITIVE, 'incident', $id);

        return $this->render('command/incident.html.twig', [
            'incident' => $incident,
            'detail' => $this->view->detail($incident, $sources->findByIncident($incident), $requests->statsForIncident($incident)),
            'waves' => $waves->findByIncident($incident),
            'auditEntries' => $this->isGranted('ROLE_ADMIN') ? $auditLog->findForSubject('incident', $id, 20) : [],
            'auditLabels' => AuditLogger::LABELS,
            'research' => ['available' => $research->isAvailable(), 'provider' => $research->providerName()],
            'sourceKinds' => ['official' => 'Źródło oficjalne', 'infrastructure_operator' => 'Operator infrastruktury', 'media' => 'Media', 'social' => 'Social media', 'other' => 'Inne'],
        ]);
    }

    #[Route('/{id}/alert', name: 'command_incident_alert', methods: ['POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function alert(#[CurrentUser] Operator $operator, Incident $incident, Request $request, AlertPublisher $publisher): Response
    {
        $id = $incident->getId()->toRfc4122();
        $this->assertCsrf('alert'.$id, $request);

        $dto = new CreateAlertRequest(
            title: $request->request->getString('title'),
            body: $request->request->getString('body'),
            severity: $request->request->getString('severity', 'warning'),
            incidentId: $id,
            ttlMinutes: $request->request->getInt('ttlMinutes', 180),
        );
        if ($this->flashErrors($dto)) {
            return $this->redirectToRoute('command_incident', ['id' => $id]);
        }

        $alert = $publisher->publish($dto, $operator->getUserIdentifier());
        $this->audit->log($operator, AuditLogger::PUBLISH_ALERT, 'alert', $alert->getId()->toRfc4122(), ['incidentId' => $id]);
        $this->addFlash('success', 'Komunikat został wysłany do urządzeń w obszarze zdarzenia.');

        return $this->redirectToRoute('command_incident', ['id' => $id]);
    }

    #[Route('/{id}/sources', name: 'command_incident_add_source', methods: ['POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function addSource(#[CurrentUser] Operator $operator, Incident $incident, Request $request, ExternalSourceRecorder $recorder): Response
    {
        $id = $incident->getId()->toRfc4122();
        $this->assertCsrf('source'.$id, $request);

        $dto = new AddExternalSourceRequest(
            url: $request->request->getString('url'),
            title: $request->request->getString('title'),
            kind: $request->request->getString('kind', 'official'),
            credibility: (float) $request->request->get('credibility', 0.9),
            publisher: self::nullableString($request, 'publisher'),
            excerpt: self::nullableString($request, 'excerpt'),
        );
        if ($this->flashErrors($dto)) {
            return $this->redirectToRoute('command_incident', ['id' => $id]);
        }

        $recorder->record($incident, $dto->url, $dto->title, $dto->kind, $dto->credibility, 'operator', $dto->publisher, $dto->excerpt);
        $this->audit->log($operator, AuditLogger::ADD_SOURCE, 'incident', $id, ['url' => $dto->url]);
        $this->addFlash('success', 'Źródło dodane. Wskaźnik wiarygodności zostanie przeliczony.');

        return $this->redirectToRoute('command_incident', ['id' => $id]);
    }

    #[Route('/{id}/research', name: 'command_incident_research', methods: ['POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function research(#[CurrentUser] Operator $operator, Incident $incident, Request $request, ResearchRequester $research): Response
    {
        $id = $incident->getId()->toRfc4122();
        $this->assertCsrf('research'.$id, $request);

        if (!$research->isAvailable()) {
            $this->addFlash('error', \sprintf('Dostawca research AI (%s) nie ma skonfigurowanego klucza API.', $research->providerName()));

            return $this->redirectToRoute('command_incident', ['id' => $id]);
        }

        $research->requestAgain($incident);
        $this->audit->log($operator, AuditLogger::RERUN_RESEARCH, 'incident', $id);
        $this->addFlash('info', 'Research AI uruchomiony ponownie. Wyniki pojawią się po zakończeniu pracy workera.');

        return $this->redirectToRoute('command_incident', ['id' => $id]);
    }

    #[Route('/{id}/resolve', name: 'command_incident_resolve', methods: ['POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function resolve(#[CurrentUser] Operator $operator, Incident $incident, Request $request, MessageBusInterface $bus): Response
    {
        $id = $incident->getId()->toRfc4122();
        $this->assertCsrf('resolve'.$id, $request);

        $resolution = IncidentResolution::tryFrom($request->request->getString('resolution', 'confirmed')) ?? IncidentResolution::Confirmed;
        $bus->dispatch(new ResolveIncident($id, $operator->getUserIdentifier(), $resolution->value));
        $this->audit->log($operator, AuditLogger::RESOLVE_INCIDENT, 'incident', $id, ['resolution' => $resolution->value]);
        $this->addFlash('success', \sprintf('Incydent oznaczony do zamknięcia: %s.', mb_strtolower($resolution->label())));

        return $this->redirectToRoute('command_incident', ['id' => $id]);
    }
}
