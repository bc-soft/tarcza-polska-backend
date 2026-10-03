<?php

declare(strict_types=1);

namespace App\Audit\Repository;

use App\Audit\Dto\AuditSearchCriteria;
use App\Audit\Entity\AuditLogEntry;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/** @extends ServiceEntityRepository<AuditLogEntry> */
final class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLogEntry::class);
    }

    /** @return list<AuditLogEntry> newest first */
    public function search(AuditSearchCriteria $criteria): array
    {
        $qb = $this->createQueryBuilder('a')->orderBy('a.createdAt', SortDirection::Descending)->setMaxResults($criteria->limit);

        if (null !== $criteria->actor) {
            $qb->andWhere('LOWER(a.actor) LIKE :actor')->setParameter('actor', '%'.mb_strtolower($criteria->actor).'%');
        }
        if (null !== $criteria->action) {
            $qb->andWhere('a.action = :action')->setParameter('action', $criteria->action);
        }
        if (null !== $criteria->subjectType) {
            $qb->andWhere('a.subjectType = :subjectType')->setParameter('subjectType', $criteria->subjectType);
        }

        /** @var list<AuditLogEntry> */
        return $qb->getQuery()->getResult();
    }

    /** @return list<AuditLogEntry> newest first */
    public function findForSubject(string $subjectType, string $subjectId, int $limit = 50): array
    {
        /** @var list<AuditLogEntry> */
        return $this->createQueryBuilder('a')
            ->where('a.subjectType = :type')
            ->andWhere('a.subjectId = :id')
            ->setParameter('type', $subjectType)
            ->setParameter('id', $subjectId)
            ->orderBy('a.createdAt', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<string> */
    public function distinctActions(): array
    {
        /** @var list<array{action: string}> $rows */
        $rows = $this->createQueryBuilder('a')->select('DISTINCT a.action AS action')->orderBy('a.action')->getQuery()->getArrayResult();

        return array_map(static fn (array $r) => $r['action'], $rows);
    }

    /** @return array<string, int> action => count within the window */
    public function countByActionSince(DateTimeImmutable $since): array
    {
        /** @var list<array{action: string, n: string|int}> $rows */
        $rows = $this->createQueryBuilder('a')
            ->select('a.action AS action, COUNT(a.id) AS n')
            ->where('a.createdAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('a.action')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[$row['action']] = (int) $row['n'];
        }

        return $out;
    }
}
