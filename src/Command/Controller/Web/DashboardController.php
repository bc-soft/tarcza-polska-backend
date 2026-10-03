<?php

declare(strict_types=1);

namespace App\Command\Controller\Web;

use App\Alerting\Dto\CreateAlertRequest;
use App\Alerting\Repository\AlertRepository;
use App\Alerting\Service\AlertPublisher;
use App\Audit\Service\AuditLogger;
use App\Command\View\IncidentCommandView;
use App\Identity\Entity\Operator;
use App\Identity\Repository\DeviceRepository;
use App\Incident\Entity\Incident;
use App\Incident\Repository\IncidentRepository;
use App\Intelligence\Repository\ExternalSourceRepository;
use App\Reporting\Repository\ReportRepository;
use App\Verification\Repository\VerificationRequestRepository;
use App\Verification\Repository\VerificationWaveRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Twig Command Center. Live updates come from Mercure (topic "incidents"); actions post back here.
 */
#[Route('/command')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly IncidentRepository $incidents,
        private readonly IncidentCommandView $view,
    ) {
    }

    #[Route('', name: 'command_dashboard', methods: ['GET'])]
    public function dashboard(DeviceRepository $devices, ReportRepository $reports, AlertRepository $alerts): Response
    {
        $open = $this->incidents->findOpen();

        return $this->render('command/dashboard.html.twig', [
            'incidents' => array_map($this->view->summary(...), $open),
            'alerts' => $alerts->findRecent(10),
            'stats' => [
                'activeDevices24h' => $devices->countActive(24),
                'reportsLastHour' => $reports->countSince(new DateTimeImmutable('-1 hour')),
                'openIncidents' => \count($open),
            ],
        ]);
    }

    #[Route('/incidents/{id}', name: 'command_incident', methods: ['GET'])]
    public function incident(
        #[CurrentUser]
        Operator $operator,
        Incident $incident,
        ExternalSourceRepository $sources,
        VerificationRequestRepository $requests,
        VerificationWaveRepository $waves,
        AuditLogger $audit,
    ): Response {
        $audit->log($operator, AuditLogger::VIEW_SENSITIVE, 'incident', $incident->getId()->toRfc4122());

        return $this->render('command/incident.html.twig', [
            'incident' => $incident,
            'detail' => $this->view->detail($incident, $sources->findByIncident($incident), $requests->statsForIncident($incident)),
            'waves' => $waves->findByIncident($incident),
        ]);
    }

    #[Route('/incidents/{id}/alert', name: 'command_incident_alert', methods: ['POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function alert(#[CurrentUser] Operator $operator, Incident $incident, Request $request, AlertPublisher $publisher, AuditLogger $audit): Response
    {
        if (!$this->isCsrfTokenValid('alert'.$incident->getId()->toRfc4122(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $dto = new CreateAlertRequest(
            title: (string) $request->request->get('title', ''),
            body: (string) $request->request->get('body', ''),
            severity: (string) $request->request->get('severity', 'warning'),
            incidentId: $incident->getId()->toRfc4122(),
            ttlMinutes: (int) $request->request->get('ttlMinutes', 180),
        );
        $alert = $publisher->publish($dto, $operator->getUserIdentifier());
        $audit->log($operator, AuditLogger::PUBLISH_ALERT, 'alert', $alert->getId()->toRfc4122(), ['incidentId' => $dto->incidentId]);

        $this->addFlash('success', 'Komunikat został wysłany do urządzeń w obszarze zdarzenia.');

        return $this->redirectToRoute('command_incident', ['id' => $incident->getId()->toRfc4122()]);
    }
}
