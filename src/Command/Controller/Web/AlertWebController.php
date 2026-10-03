<?php

declare(strict_types=1);

namespace App\Command\Controller\Web;

use App\Alerting\Dto\CreateAlertRequest;
use App\Alerting\Repository\AlertRepository;
use App\Alerting\Service\AlertPublisher;
use App\Audit\Service\AuditLogger;
use App\Command\View\IncidentCommandView;
use App\Identity\Entity\Operator;
use App\Incident\Entity\Incident;
use App\Incident\Repository\IncidentRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Area alerts: history and a stand-alone "publish to an incident area" form. */
#[Route('/command/alerts')]
final class AlertWebController extends AbstractCommandController
{
    public function __construct(
        private readonly AlertRepository $alerts,
        private readonly IncidentRepository $incidents,
        private readonly IncidentCommandView $view,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('', name: 'command_alert_list', methods: ['GET'])]
    public function list(): Response
    {
        $withArea = array_values(array_filter($this->incidents->findOpen(), static fn (Incident $i) => null !== $i->getArea()));

        return $this->render('command/alert_list.html.twig', [
            'alerts' => $this->alerts->findRecent(100),
            'activeCount' => $this->alerts->countActive(),
            'targets' => array_map($this->view->summary(...), $withArea),
        ]);
    }

    #[Route('', name: 'command_alert_create', methods: ['POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function create(#[CurrentUser] Operator $operator, Request $request, AlertPublisher $publisher): Response
    {
        $this->assertCsrf('alert_create', $request);

        $dto = new CreateAlertRequest(
            title: $request->request->getString('title'),
            body: $request->request->getString('body'),
            severity: $request->request->getString('severity', 'warning'),
            incidentId: self::nullableString($request, 'incidentId'),
            ttlMinutes: $request->request->getInt('ttlMinutes', 180),
        );
        if ($this->flashErrors($dto)) {
            return $this->redirectToRoute('command_alert_list');
        }

        $alert = $publisher->publish($dto, $operator->getUserIdentifier());
        $this->audit->log($operator, AuditLogger::PUBLISH_ALERT, 'alert', $alert->getId()->toRfc4122(), ['incidentId' => $dto->incidentId]);
        $this->addFlash('success', 'Komunikat opublikowany. Dostarczanie push trwa w tle.');

        return $this->redirectToRoute('command_alert_list');
    }
}
