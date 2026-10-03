<?php

declare(strict_types=1);

namespace App\Command\Controller\Web;

use App\Alerting\Entity\Alert;
use App\Alerting\Repository\AlertRepository;
use App\Alerting\View\AlertView;
use App\Command\View\IncidentCommandView;
use App\Fuel\Repository\FuelStationRepository;
use App\Fuel\View\FuelStationView;
use App\Identity\Repository\DeviceRepository;
use App\Incident\Repository\IncidentRepository;
use App\Reporting\Repository\ReportRepository;
use App\Shelter\Repository\ShelterRepository;
use App\Shelter\View\ShelterView;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Twig Command Center: situational map. Live updates come from Mercure (topic "incidents").
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
    public function dashboard(DeviceRepository $devices, ReportRepository $reports, AlertRepository $alerts, ShelterRepository $shelters, FuelStationRepository $fuelStations): Response
    {
        $open = $this->incidents->findOpen();
        $shelterCounts = $shelters->countByStatus();

        return $this->render('command/dashboard.html.twig', [
            'incidents' => array_map($this->view->summary(...), $open),
            'alerts' => $alerts->findRecent(8),
            'alertFeatures' => array_map(AlertView::toFeature(...), array_values(array_filter($alerts->findRecent(50), static fn (Alert $a) => $a->isActive()))),
            'shelterFeatures' => array_map(ShelterView::toFeature(...), $shelters->findAllOrdered()),
            'fuelFeatures' => array_map(FuelStationView::toFeature(...), $fuelStations->findAllOrdered()),
            'stats' => [
                'activeDevices24h' => $devices->countActive(24),
                'reportsLastHour' => $reports->countSince(new DateTimeImmutable('-1 hour')),
                'openIncidents' => \count($open),
                'activeAlerts' => $alerts->countActive(),
                'sheltersOpen' => $shelterCounts['open'] ?? 0,
                'sheltersTotal' => array_sum($shelterCounts),
            ],
        ]);
    }
}
