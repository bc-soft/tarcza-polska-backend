<?php

declare(strict_types=1);

namespace App\Fuel\Service;

use App\Fuel\Enum\FuelAvailability;
use App\Fuel\Enum\FuelType;
use App\Fuel\Repository\FuelStationRepository;
use App\Shared\Poi\PoiKind;
use App\Shared\Poi\PoiStatusUpdaterInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Crowd answer "is there fuel at station X?" -> per-fuel availability on that station.
 */
final readonly class FuelStationStatusUpdater implements PoiStatusUpdaterInterface
{
    public function __construct(private FuelStationRepository $stations)
    {
    }

    public function kind(): PoiKind
    {
        return PoiKind::FuelStation;
    }

    public function applyAnswer(Uuid $poiId, bool $problemPresent, array $context = []): void
    {
        $station = $this->stations->find($poiId);
        if (null === $station) {
            return;
        }
        $types = FuelType::fromValues($context);
        if ([] === $types) {
            $types = [] !== $station->getFuelTypes() ? $station->getFuelTypes() : FuelType::cases();
        }
        $station->confirmAvailability($types, $problemPresent ? FuelAvailability::Unavailable : FuelAvailability::Available);
    }
}
