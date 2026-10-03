<?php

declare(strict_types=1);

namespace App\Shelter\Repository;

use App\Shelter\Entity\ShelterStatusReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/** @extends ServiceEntityRepository<ShelterStatusReport> */
final class ShelterStatusReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShelterStatusReport::class);
    }

    /** @return list<ShelterStatusReport> newest first, shelter pre-loaded */
    public function findRecent(int $limit = 30): array
    {
        /** @var list<ShelterStatusReport> */
        return $this->createQueryBuilder('r')
            ->addSelect('s')
            ->join('r.shelter', 's')
            ->orderBy('r.createdAt', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
