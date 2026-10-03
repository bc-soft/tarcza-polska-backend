<?php

declare(strict_types=1);

namespace App\Alerting\Repository;

use App\Alerting\Entity\Alert;
use App\Shared\Geo\BoundingBox;
use App\Shared\Geo\Point;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Alert> */
final class AlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Alert::class);
    }

    /** @return list<Alert> */
    public function findActiveContaining(Point $point): array
    {
        $sql = 'SELECT id FROM alert WHERE expires_at > NOW() AND ST_Intersects(area, ST_SetSRID(ST_MakePoint(:lng, :lat), 4326))';
        /** @var list<string> $ids */
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, ['lng' => $point->lng, 'lat' => $point->lat]);

        return $this->byIds($ids);
    }

    /** @return list<Alert> */
    public function findActiveInBoundingBox(BoundingBox $bbox): array
    {
        $sql = \sprintf('SELECT id FROM alert WHERE expires_at > NOW() AND ST_Intersects(area, %s)', $bbox->envelopeSql());
        /** @var list<string> $ids */
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, $bbox->params());

        return $this->byIds($ids);
    }

    /** @return list<Alert> */
    public function findRecent(int $limit = 50): array
    {
        /** @var list<Alert> */
        return $this->createQueryBuilder('a')->orderBy('a.createdAt', 'DESC')->setMaxResults($limit)->getQuery()->getResult();
    }

    /**
     * @param list<string> $ids
     *
     * @return list<Alert>
     */
    private function byIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<Alert> */
        return $this->createQueryBuilder('a')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (string $id) => Uuid::fromString($id), $ids))
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
