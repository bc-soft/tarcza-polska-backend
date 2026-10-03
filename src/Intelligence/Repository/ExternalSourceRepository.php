<?php

declare(strict_types=1);

namespace App\Intelligence\Repository;

use App\Incident\Entity\Incident;
use App\Intelligence\Entity\ExternalSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/** @extends ServiceEntityRepository<ExternalSource> */
final class ExternalSourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExternalSource::class);
    }

    /** @return list<ExternalSource> */
    public function findByIncident(Incident $incident): array
    {
        /** @var list<ExternalSource> */
        return $this->createQueryBuilder('s')
            ->where('s.incident = :incident')
            ->setParameter('incident', $incident)
            ->orderBy('s.credibility', SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    /** @return array{credibility: float, count: int} */
    public function bestCredibility(Incident $incident): array
    {
        /** @var array{best: string|float|null, cnt: string|int} $row */
        $row = $this->createQueryBuilder('s')
            ->select('COALESCE(MAX(s.credibility), 0) AS best, COUNT(s.id) AS cnt')
            ->where('s.incident = :incident')
            ->setParameter('incident', $incident)
            ->getQuery()
            ->getSingleResult();

        return ['credibility' => (float) $row['best'], 'count' => (int) $row['cnt']];
    }
}
