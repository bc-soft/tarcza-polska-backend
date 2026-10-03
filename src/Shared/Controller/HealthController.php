<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    public function __construct(private readonly Connection $connection)
    {
    }

    #[Route('/api/v1/health', name: 'api_health', methods: ['GET'])]
    #[OA\Get(summary: 'Liveness + database/PostGIS/H3 readiness', security: [])]
    #[OA\Response(response: 200, description: 'Status of the spatial stack', content: new OA\JsonContent(ref: '#/components/schemas/HealthStatus'))]
    public function __invoke(): JsonResponse
    {
        /** @var array{postgis: string|null, h3: string|null, h3_postgis: string|null} $row */
        $row = $this->connection->fetchAssociative(
            "SELECT (SELECT extversion FROM pg_extension WHERE extname = 'postgis') AS postgis,
                    (SELECT extversion FROM pg_extension WHERE extname = 'h3') AS h3,
                    (SELECT extversion FROM pg_extension WHERE extname = 'h3_postgis') AS h3_postgis",
        );

        return new JsonResponse([
            'status' => null !== $row['postgis'] && null !== $row['h3_postgis'] ? 'ok' : 'degraded',
            'postgis' => $row['postgis'],
            'h3' => $row['h3'],
            'h3Postgis' => $row['h3_postgis'],
            'time' => new DateTimeImmutable()->format(\DATE_ATOM),
        ]);
    }
}
