<?php

declare(strict_types=1);

namespace App\Shelter\Repository;

use App\Shared\Geo\BoundingBox;
use App\Shared\Geo\Point;
use App\Shelter\Entity\Shelter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Shelter> */
final class ShelterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shelter::class);
    }

    /** @return list<Shelter> */
    public function findInBoundingBox(BoundingBox $bbox, int $limit = 500): array
    {
        $sql = \sprintf('SELECT id FROM shelter WHERE ST_Intersects(location, %s) LIMIT %d', $bbox->envelopeSql(), $limit);
        /** @var list<string> $ids */
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, $bbox->params());

        return $this->byIds($ids);
    }

    /** @return list<Shelter> nearest first */
    public function findNearest(Point $point, int $limit = 5): array
    {
        $sql = 'SELECT id FROM shelter ORDER BY location::geography <-> ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography LIMIT :limit';
        /** @var list<string> $ids */
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, ['lng' => $point->lng, 'lat' => $point->lat, 'limit' => $limit]);

        $byId = [];
        foreach ($this->byIds($ids) as $shelter) {
            $byId[$shelter->getId()->toRfc4122()] = $shelter;
        }

        return array_values(array_filter(array_map(static fn (string $id) => $byId[$id] ?? null, $ids)));
    }

    /**
     * @param list<string> $ids
     *
     * @return list<Shelter>
     */
    private function byIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<Shelter> */
        return $this->createQueryBuilder('s')
            ->where('s.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (string $id) => Uuid::fromString($id), $ids))
            ->getQuery()
            ->getResult();
    }
}
