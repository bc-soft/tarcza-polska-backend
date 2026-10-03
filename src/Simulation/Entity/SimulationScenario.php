<?php

declare(strict_types=1);

namespace App\Simulation\Entity;

use App\Reporting\Enum\ReportType;
use App\Shared\Geo\Point;
use App\Simulation\Repository\SimulationScenarioRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Ground truth for the demo crowd: a circular problem area. Simulated devices answer verification
 * questions according to whether they stand inside it (with configurable noise).
 */
#[ORM\Entity(repositoryClass: SimulationScenarioRepository::class)]
#[ORM\Table(name: 'simulation_scenario')]
class SimulationScenario
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 32, enumType: ReportType::class)]
    private ReportType $type;

    #[ORM\Column(type: 'geo_point')]
    private Point $center;

    #[ORM\Column(type: Types::INTEGER)]
    private int $radiusMeters;

    /** Probability that a simulated citizen answers "correctly" (inside -> problem, outside -> no problem). */
    #[ORM\Column(type: Types::FLOAT)]
    private float $accuracy;

    /** Probability of answering "unknown". */
    #[ORM\Column(type: Types::FLOAT)]
    private float $unknownRate;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(string $name, ReportType $type, Point $center, int $radiusMeters, float $accuracy = 0.9, float $unknownRate = 0.08)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->type = $type;
        $this->center = $center;
        $this->radiusMeters = $radiusMeters;
        $this->accuracy = $accuracy;
        $this->unknownRate = $unknownRate;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ReportType
    {
        return $this->type;
    }

    public function getCenter(): Point
    {
        return $this->center;
    }

    public function getRadiusMeters(): int
    {
        return $this->radiusMeters;
    }

    public function getAccuracy(): float
    {
        return $this->accuracy;
    }

    public function getUnknownRate(): float
    {
        return $this->unknownRate;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function contains(Point $point): bool
    {
        return $this->center->distanceTo($point) <= $this->radiusMeters;
    }
}
