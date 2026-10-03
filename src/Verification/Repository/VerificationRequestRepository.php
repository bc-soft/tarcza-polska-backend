<?php

declare(strict_types=1);

namespace App\Verification\Repository;

use App\Identity\Entity\Device;
use App\Verification\Entity\VerificationRequest;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<VerificationRequest> */
final class VerificationRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VerificationRequest::class);
    }

    /** @return list<VerificationRequest> */
    public function findPendingForDevice(Device $device): array
    {
        /** @var list<VerificationRequest> */
        return $this->createQueryBuilder('r')
            ->where('r.device = :device')
            ->andWhere('r.answeredAt IS NULL')
            ->andWhere('r.expiresAt > :now')
            ->setParameter('device', $device)
            ->setParameter('now', new DateTimeImmutable())
            ->orderBy('r.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Pending requests addressed to simulated devices, sent at least $minAgeSeconds ago (demo crowd).
     *
     * @return list<VerificationRequest>
     */
    public function findPendingSimulated(int $minAgeSeconds, int $limit = 200): array
    {
        /** @var list<VerificationRequest> */
        return $this->createQueryBuilder('r')
            ->join('r.device', 'd')
            ->where('d.simulated = true')
            ->andWhere('r.answeredAt IS NULL')
            ->andWhere('r.expiresAt > :now')
            ->andWhere('r.sentAt <= :maxSent')
            ->setParameter('now', new DateTimeImmutable())
            ->setParameter('maxSent', new DateTimeImmutable(\sprintf('-%d seconds', $minAgeSeconds)))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return array{sent: int, answered: int} */
    public function statsForIncident(\App\Incident\Entity\Incident $incident): array
    {
        /** @var array{sent: string|int, answered: string|int} $row */
        $row = $this->createQueryBuilder('r')
            ->select('COUNT(r.id) AS sent, COUNT(r.answeredAt) AS answered')
            ->where('r.incident = :incident')
            ->setParameter('incident', $incident)
            ->getQuery()
            ->getSingleResult();

        return ['sent' => (int) $row['sent'], 'answered' => (int) $row['answered']];
    }
}
