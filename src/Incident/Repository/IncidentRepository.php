<?php

declare(strict_types=1);

namespace App\Incident\Repository;

use App\Incident\Dto\IncidentSearchCriteria;
use App\Incident\Entity\Incident;
use App\Incident\Enum\IncidentStatus;
use App\Reporting\Enum\ReportType;
use App\Shared\Geo\BoundingBox;
use App\Shared\Geo\Point;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Incident> */
final class IncidentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Incident::class);
    }

    public function save(Incident $incident, bool $flush = false): void
    {
        $this->getEntityManager()->persist($incident);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Nearest open incident of the same type within $radiusMeters whose last activity is within $windowMinutes.
     */
    public function findClusterCandidate(ReportType $type, Point $point, int $radiusMeters, int $windowMinutes): ?Incident
    {
        $sql = <<<SQL
            SELECT id
            FROM incident
            WHERE type = :type
              AND status <> :resolved
              AND last_activity_at > :since
              AND ST_DWithin(centroid::geography, ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography, :radius)
            ORDER BY ST_Distance(centroid::geography, ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography) ASC
            LIMIT 1
            SQL;

        /** @var string|false $id */
        $id = $this->getEntityManager()->getConnection()->fetchOne($sql, [
            'type' => $type->value,
            'resolved' => IncidentStatus::Resolved->value,
            'since' => new DateTimeImmutable(\sprintf('-%d minutes', $windowMinutes))->format('Y-m-d H:i:s'),
            'lng' => $point->lng,
            'lat' => $point->lat,
            'radius' => $radiusMeters,
        ]);

        return false === $id ? null : $this->find(Uuid::fromString($id));
    }

    /** @return list<Incident> */
    public function findOpen(): array
    {
        /** @var list<Incident> */
        return $this->createQueryBuilder('i')
            ->where('i.status <> :resolved')
            ->setParameter('resolved', IncidentStatus::Resolved)
            ->orderBy('i.lastActivityAt', SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Incident> newest activity first */
    public function search(IncidentSearchCriteria $criteria): array
    {
        $qb = $this->createQueryBuilder('i')->orderBy('i.lastActivityAt', SortDirection::Descending)->setMaxResults($criteria->limit);

        if (null !== $criteria->status) {
            $qb->andWhere('i.status = :status')->setParameter('status', $criteria->status);
        } elseif (!$criteria->includeResolved) {
            $qb->andWhere('i.status <> :resolved')->setParameter('resolved', IncidentStatus::Resolved);
        }
        if (null !== $criteria->type) {
            $qb->andWhere('i.type = :type')->setParameter('type', $criteria->type);
        }
        if (null !== $criteria->level) {
            $qb->andWhere('i.confidenceLevel = :level')->setParameter('level', $criteria->level);
        }

        /** @var list<Incident> */
        return $qb->getQuery()->getResult();
    }

    /** @return array<string, int> status value => count */
    public function countByStatus(): array
    {
        /** @var list<array{status: IncidentStatus, n: string|int}> $rows */
        $rows = $this->createQueryBuilder('i')
            ->select('i.status AS status, COUNT(i.id) AS n')
            ->groupBy('i.status')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[$row['status']->value] = (int) $row['n'];
        }

        return $out;
    }

    /** @return list<Incident> */
    public function findRecent(int $limit = 100): array
    {
        /** @var list<Incident> */
        return $this->createQueryBuilder('i')
            ->orderBy('i.lastActivityAt', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Open incidents whose centroid or area intersects the bbox.
     *
     * @return list<Incident>
     */
    public function findOpenInBoundingBox(BoundingBox $bbox): array
    {
        $sql = \sprintf(
            'SELECT id FROM incident WHERE status <> :resolved AND (ST_Intersects(centroid, %1$s) OR (area IS NOT NULL AND ST_Intersects(area, %1$s)))',
            $bbox->envelopeSql(),
        );

        /** @var list<string> $ids */
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            $sql,
            ['resolved' => IncidentStatus::Resolved->value] + $bbox->params(),
        );

        if ([] === $ids) {
            return [];
        }

        /** @var list<Incident> */
        return $this->createQueryBuilder('i')
            ->where('i.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (string $id) => Uuid::fromString($id), $ids))
            ->orderBy('i.confidenceScore', SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    /**
     * Open incidents whose area contains the point (used for "what is happening where I am").
     *
     * @return list<Incident>
     */
    public function findOpenContaining(Point $point): array
    {
        $sql = <<<SQL
            SELECT id FROM incident
            WHERE status <> :resolved
              AND area IS NOT NULL
              AND ST_Intersects(area, ST_SetSRID(ST_MakePoint(:lng, :lat), 4326))
            SQL;

        /** @var list<string> $ids */
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, [
            'resolved' => IncidentStatus::Resolved->value,
            'lng' => $point->lng,
            'lat' => $point->lat,
        ]);

        if ([] === $ids) {
            return [];
        }

        /** @var list<Incident> */
        return $this->createQueryBuilder('i')
            ->where('i.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (string $id) => Uuid::fromString($id), $ids))
            ->getQuery()
            ->getResult();
    }
}
