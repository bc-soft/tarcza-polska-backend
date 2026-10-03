<?php

declare(strict_types=1);

namespace App\Simulation\Repository;

use App\Reporting\Enum\ReportType;
use App\Simulation\Entity\SimulationScenario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SimulationScenario> */
final class SimulationScenarioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SimulationScenario::class);
    }

    /** @return list<SimulationScenario> */
    public function findActive(?ReportType $type = null): array
    {
        $qb = $this->createQueryBuilder('s')->where('s.active = true');
        if (null !== $type) {
            $qb->andWhere('s.type = :type')->setParameter('type', $type);
        }

        /** @var list<SimulationScenario> */
        return $qb->getQuery()->getResult();
    }

    public function deactivateAll(): int
    {
        return (int) $this->createQueryBuilder('s')->update()->set('s.active', 'false')->where('s.active = true')->getQuery()->execute();
    }
}
