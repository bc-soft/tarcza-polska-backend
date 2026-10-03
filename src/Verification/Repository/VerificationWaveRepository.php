<?php

declare(strict_types=1);

namespace App\Verification\Repository;

use App\Incident\Entity\Incident;
use App\Verification\Entity\VerificationWave;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<VerificationWave> */
final class VerificationWaveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VerificationWave::class);
    }

    public function findOpenFor(Incident $incident): ?VerificationWave
    {
        /** @var VerificationWave|null */
        return $this->createQueryBuilder('w')
            ->where('w.incident = :incident')
            ->andWhere('w.closedAt IS NULL')
            ->setParameter('incident', $incident)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<VerificationWave> */
    public function findExpiredOpen(): array
    {
        /** @var list<VerificationWave> */
        return $this->createQueryBuilder('w')
            ->where('w.closedAt IS NULL')
            ->andWhere('w.expiresAt <= :now')
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /** @return list<VerificationWave> */
    public function findByIncident(Incident $incident): array
    {
        /** @var list<VerificationWave> */
        return $this->createQueryBuilder('w')
            ->where('w.incident = :incident')
            ->setParameter('incident', $incident)
            ->orderBy('w.ring', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
