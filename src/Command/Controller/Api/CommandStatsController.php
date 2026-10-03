<?php

declare(strict_types=1);

namespace App\Command\Controller\Api;

use App\Identity\Repository\DeviceRepository;
use App\Incident\Repository\IncidentRepository;
use App\Reporting\Repository\ReportRepository;
use DateTimeImmutable;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Command')]
final class CommandStatsController
{
    public function __construct(
        private readonly DeviceRepository $devices,
        private readonly ReportRepository $reports,
        private readonly IncidentRepository $incidents,
    ) {
    }

    #[Route('/api/command/stats', name: 'api_command_stats', methods: ['GET'])]
    #[OA\Get(summary: 'Dashboard counters')]
    #[OA\Response(response: 200, description: 'Counters')]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'activeDevices24h' => $this->devices->countActive(24),
            'reportsLastHour' => $this->reports->countSince(new DateTimeImmutable('-1 hour')),
            'openIncidents' => \count($this->incidents->findOpen()),
        ]);
    }
}
