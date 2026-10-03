<?php

declare(strict_types=1);

namespace App\Reporting\Repository;

use App\Incident\Entity\Incident;
use App\Reporting\Entity\Report;
use App\Reporting\Entity\ReportPhoto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/** @extends ServiceEntityRepository<ReportPhoto> */
final class ReportPhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReportPhoto::class);
    }

    public function countForReport(Report $report): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.report = :report')
            ->setParameter('report', $report)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<ReportPhoto> newest first */
    public function findByIncident(Incident $incident, bool $includeUnsafe = false): array
    {
        $qb = $this->createQueryBuilder('p')
            ->join('p.report', 'r')
            ->where('r.incident = :incident')
            ->setParameter('incident', $incident)
            ->orderBy('p.createdAt', SortDirection::Descending);
        if (!$includeUnsafe) {
            $qb->andWhere('p.unsafe = false');
        }

        /** @var list<ReportPhoto> */
        return $qb->getQuery()->getResult();
    }
}
