<?php

declare(strict_types=1);

namespace App\Incident\Service;

use App\Incident\Entity\Incident;
use App\Incident\Enum\CellState;
use App\Shared\Geo\H3;

/**
 * Dynamic area = union of positive cells. Frontier = unknown neighbours of the area (where to ask next).
 */
final readonly class AreaCalculator
{
    public function __construct(private H3 $h3)
    {
    }

    public function recompute(Incident $incident): void
    {
        if ($incident->isPointScoped()) {
            $incident->setArea(null); // a station or a shelter is a pin, never a polygon

            return;
        }
        $incident->setArea($this->h3->cellsToMultiPolygon($incident->positiveCells()));
    }

    /**
     * Cells adjacent to the positive area that have no verdict yet.
     *
     * @return list<string>
     */
    public function frontier(Incident $incident): array
    {
        $positive = $incident->positiveCells();
        if ([] === $positive) {
            $positive = [$incident->getCenterCell()];
        }

        $decided = array_flip([...$positive, ...$incident->cellsInState(CellState::Negative)]);

        return array_values(array_filter(
            $this->h3->frontier($positive),
            static fn (string $cell) => !isset($decided[$cell]),
        ));
    }
}
