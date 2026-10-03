<?php

declare(strict_types=1);

namespace App\Incident\Repository;

use App\Incident\Entity\Incident;
use App\Incident\Entity\IncidentEvent;
use App\Incident\Enum\IncidentEventType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/** @extends ServiceEntityRepository<IncidentEvent> */
final class IncidentEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IncidentEvent::class);
    }

    /** @return list<IncidentEvent> oldest first */
    public function findByIncident(Incident $incident, bool $publicOnly = false): array
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.incident = :incident')
            ->setParameter('incident', $incident)
            ->orderBy('e.createdAt', SortDirection::Ascending)
            ->addOrderBy('e.id', SortDirection::Ascending);

        if ($publicOnly) {
            $qb->andWhere('e.type IN (:types)')
                ->setParameter('types', array_values(array_filter(IncidentEventType::cases(), static fn (IncidentEventType $t) => $t->isPublic())));
        }

        /** @var list<IncidentEvent> */
        return $qb->getQuery()->getResult();
    }

    public function findLastOfType(Incident $incident, IncidentEventType $type): ?IncidentEvent
    {
        /** @var IncidentEvent|null */
        return $this->createQueryBuilder('e')
            ->where('e.incident = :incident')
            ->andWhere('e.type = :type')
            ->setParameter('incident', $incident)
            ->setParameter('type', $type)
            ->orderBy('e.createdAt', SortDirection::Descending)
            ->addOrderBy('e.id', SortDirection::Descending)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
