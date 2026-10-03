<?php

declare(strict_types=1);

namespace App\Simulation\Fixture;

use App\Shared\Geo\Point;
use LogicException;

/** Seeded randomness so every `tarcza:fixtures:load --seed=N` produces the same panel. */
final class FixtureRandom
{
    private \Random\Randomizer $rng;

    public function __construct(int $seed)
    {
        $this->rng = new \Random\Randomizer(new \Random\Engine\Mt19937($seed));
    }

    public function int(int $min, int $max): int
    {
        return $this->rng->getInt($min, $max);
    }

    public function float(float $min = 0.0, float $max = 1.0): float
    {
        return $min + ($max - $min) * ($this->rng->getInt(0, 1_000_000) / 1_000_000);
    }

    public function chance(float $probability): bool
    {
        return $this->float() < $probability;
    }

    /**
     * @template T
     *
     * @param list<T> $items
     *
     * @return T
     */
    public function pick(array $items): mixed
    {
        if ([] === $items) {
            throw new LogicException('pick() needs at least one item');
        }

        return $items[$this->rng->getInt(0, \count($items) - 1)];
    }

    /**
     * @template T
     *
     * @param list<T> $items
     *
     * @return list<T>
     */
    public function some(array $items, int $min, int $max): array
    {
        if ([] === $items) {
            return [];
        }
        $shuffled = $this->rng->shuffleArray($items);
        $n = min(\count($shuffled), $this->rng->getInt($min, max($min, $max)));

        return \array_slice($shuffled, 0, $n);
    }

    /** Uniform random point within $radiusMeters of $center. */
    public function around(Point $center, float $radiusMeters): Point
    {
        $r = $radiusMeters * sqrt($this->float());
        $theta = 2 * \M_PI * $this->float();
        $dLat = ($r * cos($theta)) / 111_320.0;
        $dLng = ($r * sin($theta)) / (111_320.0 * cos(deg2rad($center->lat)));

        return new Point($center->lat + $dLat, $center->lng + $dLng);
    }
}
