<?php

declare(strict_types=1);

namespace App\Fuel\Repository;

use App\Fuel\Entity\FuelStation;
use App\Shared\Geo\BoundingBox;
use App\Shared\Geo\Point;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<FuelStation> */
final class FuelStationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FuelStation::class);
    }

    public function findByExternalId(string $source, string $externalId): ?FuelStation
    {
        return $this->findOneBy(['source' => $source, 'externalId' => $externalId]);
    }

    /** @return list<FuelStation> */
    public function findInBoundingBox(BoundingBox $bbox, int $limit = 500): array
    {
        $sql = \sprintf('SELECT id FROM fuel_station WHERE ST_Intersects(location, %s) LIMIT %d', $bbox->envelopeSql(), $limit);
        /** @var list<string> $ids */
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, $bbox->params());

        return $this->byIds($ids, false);
    }

    /** @return list<FuelStation> nearest first */
    public function findWithinRadius(Point $point, int $radiusMeters, int $limit = 20, ?Uuid $exclude = null): array
    {
        $sql = <<<SQL
            SELECT id FROM fuel_station
            WHERE ST_DWithin(location::geography, ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography, :radius)
              AND (:exclude::uuid IS NULL OR id <> :exclude::uuid)
            ORDER BY location::geography <-> ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography
            LIMIT :limit
            SQL;
        /** @var list<string> $ids */
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, [
            'lng' => $point->lng, 'lat' => $point->lat, 'radius' => $radiusMeters, 'limit' => $limit, 'exclude' => $exclude?->toRfc4122(),
        ]);

        return $this->byIds($ids, true);
    }

    /** @return list<FuelStation> nearest first */
    public function findNearest(Point $point, int $limit = 10): array
    {
        $sql = 'SELECT id FROM fuel_station ORDER BY location::geography <-> ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography LIMIT :limit';
        /** @var list<string> $ids */
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, ['lng' => $point->lng, 'lat' => $point->lat, 'limit' => $limit]);

        return $this->byIds($ids, true);
    }

    /** @return list<FuelStation> */
    public function findAllOrdered(): array
    {
        /** @var list<FuelStation> */
        return $this->createQueryBuilder('f')->orderBy('f.name', SortDirection::Ascending)->getQuery()->getResult();
    }

    public function countBySource(string $source): int
    {
        return (int) $this->createQueryBuilder('f')->select('COUNT(f.id)')->where('f.source = :s')->setParameter('s', $source)->getQuery()->getSingleScalarResult();
    }

    /**
     * @param list<string> $ids
     *
     * @return list<FuelStation>
     */
    private function byIds(array $ids, bool $keepOrder): array
    {
        if ([] === $ids) {
            return [];
        }
        /** @var list<FuelStation> $rows */
        $rows = $this->createQueryBuilder('f')
            ->where('f.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (string $id) => Uuid::fromString($id), $ids))
            ->getQuery()
            ->getResult();
        if (!$keepOrder) {
            return $rows;
        }
        $byId = [];
        foreach ($rows as $row) {
            $byId[$row->getId()->toRfc4122()] = $row;
        }

        return array_values(array_filter(array_map(static fn (string $id) => $byId[$id] ?? null, $ids)));
    }
}
