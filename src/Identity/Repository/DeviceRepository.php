<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\Device;
use App\Shared\Geo\H3;
use App\Shared\Geo\Point;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Device> */
final class DeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Device::class);
    }

    public function save(Device $device, bool $flush = false): void
    {
        $this->getEntityManager()->persist($device);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Candidate devices for a verification wave: physically inside the given H3 cells,
     * seen recently, not asked within the cooldown. Returns at most $perCell devices per cell.
     *
     * @param list<string> $cells
     *
     * @return list<Device>
     */
    public function findVerificationCandidates(array $cells, int $perCell, int $cooldownMinutes, int $maxAgeHours = 12): array
    {
        if ([] === $cells) {
            return [];
        }

        $sql = <<<SQL
            SELECT id FROM (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY h3_cell ORDER BY last_seen_at DESC) AS rn
                FROM device
                WHERE h3_cell = ANY(:cells::text[])
                  AND last_seen_at > :min_seen
                  AND (last_asked_at IS NULL OR last_asked_at < :cooldown)
            ) ranked
            WHERE rn <= :per_cell
            SQL;

        $now = new DateTimeImmutable();
        /** @var list<string> $ids */
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, [
            'cells' => H3::pgArray($cells),
            'min_seen' => $now->modify(\sprintf('-%d hours', $maxAgeHours))->format('Y-m-d H:i:s'),
            'cooldown' => $now->modify(\sprintf('-%d minutes', $cooldownMinutes))->format('Y-m-d H:i:s'),
            'per_cell' => $perCell,
        ]);

        return $this->findByIds($ids);
    }

    /**
     * Devices standing at a point of interest (point verification): within $radiusMeters of the object,
     * seen recently, not asked within the cooldown. Nearest first.
     *
     * @return list<Device>
     */
    public function findNearPoint(Point $point, int $radiusMeters, int $limit, int $cooldownMinutes, int $maxAgeHours = 12): array
    {
        $sql = <<<SQL
            SELECT id FROM device
            WHERE last_location IS NOT NULL
              AND last_seen_at > :min_seen
              AND (last_asked_at IS NULL OR last_asked_at < :cooldown)
              AND ST_DWithin(last_location::geography, ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography, :radius)
            ORDER BY last_location::geography <-> ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography
            LIMIT :limit
            SQL;

        $now = new DateTimeImmutable();
        /** @var list<string> $ids */
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, [
            'min_seen' => $now->modify(\sprintf('-%d hours', $maxAgeHours))->format('Y-m-d H:i:s'),
            'cooldown' => $now->modify(\sprintf('-%d minutes', $cooldownMinutes))->format('Y-m-d H:i:s'),
            'lng' => $point->lng,
            'lat' => $point->lat,
            'radius' => $radiusMeters,
            'limit' => $limit,
        ]);

        return $this->findByIds($ids);
    }

    /**
     * Devices whose last known location lies inside the given GeoJSON geometry (alert targeting).
     *
     * @param array<string, mixed> $geometry
     *
     * @return list<Device>
     */
    public function findInsideGeometry(array $geometry, int $maxAgeHours = 24): array
    {
        $sql = <<<SQL
            SELECT id FROM device
            WHERE last_location IS NOT NULL
              AND last_seen_at > :min_seen
              AND ST_Intersects(last_location, ST_SetSRID(ST_GeomFromGeoJSON(:geom), 4326))
            SQL;

        /** @var list<string> $ids */
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, [
            'geom' => json_encode($geometry, \JSON_THROW_ON_ERROR),
            'min_seen' => new DateTimeImmutable(\sprintf('-%d hours', $maxAgeHours))->format('Y-m-d H:i:s'),
        ]);

        return $this->findByIds($ids);
    }

    /**
     * @param list<string> $ids
     *
     * @return list<Device>
     */
    private function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<Device> */
        return $this->createQueryBuilder('d')
            ->where('d.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (string $id) => Uuid::fromString($id), $ids))
            ->getQuery()
            ->getResult();
    }

    /**
     * Push-capable devices whose position went stale and that have not been reminded recently.
     *
     * @return list<Device>
     */
    public function findLocationRefreshCandidates(DateTimeImmutable $staleBefore, DateTimeImmutable $notRemindedSince, DateTimeImmutable $seenAfter, int $limit): array
    {
        /** @var list<Device> */
        return $this->createQueryBuilder('d')
            ->where('d.pushToken IS NOT NULL')
            ->andWhere('d.simulated = false')
            ->andWhere('d.locationRefreshEnabled = true')
            ->andWhere('d.locationUpdatedAt IS NOT NULL AND d.locationUpdatedAt < :staleBefore')
            ->andWhere('d.lastLocationRefreshAt IS NULL OR d.lastLocationRefreshAt < :notRemindedSince')
            ->andWhere('d.lastSeenAt > :seenAfter')
            ->setParameter('staleBefore', $staleBefore)
            ->setParameter('notRemindedSince', $notRemindedSince)
            ->setParameter('seenAfter', $seenAfter)
            ->orderBy('d.locationUpdatedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * One phone = one push token. When a re-registered or rotated token is already bound to other
     * devices (e.g. the previous installation after a 401), those rows lose it so a single phone
     * never receives the same question twice. Returns the number of detached devices.
     */
    public function detachPushTokenFromOthers(string $pushToken, Device $keep): int
    {
        return (int) $this->createQueryBuilder('d')
            ->update()
            ->set('d.pushToken', ':null')
            ->where('d.pushToken = :token')
            ->andWhere('d.id != :keep')
            ->setParameter('null', null)
            ->setParameter('token', $pushToken)
            ->setParameter('keep', $keep->getId(), 'uuid')
            ->getQuery()
            ->execute();
    }

    public function countActive(int $hours = 24): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.lastSeenAt > :since')
            ->setParameter('since', new DateTimeImmutable(\sprintf('-%d hours', $hours)))
            ->getQuery()
            ->getSingleScalarResult();
    }
}
