<?php

declare(strict_types=1);

namespace App\Fuel\Service;

use App\Fuel\Entity\FuelStation;
use App\Fuel\Repository\FuelStationRepository;
use App\Shared\Geo\Point;
use App\Shared\Poi\PoiKind;
use App\Shared\Poi\PoiLocatorInterface;
use App\Shared\Poi\PoiRef;
use Symfony\Component\Uid\Uuid;

final readonly class FuelStationLocator implements PoiLocatorInterface
{
    public function __construct(private FuelStationRepository $stations)
    {
    }

    public function kind(): PoiKind
    {
        return PoiKind::FuelStation;
    }

    public function find(Uuid $id): ?PoiRef
    {
        $station = $this->stations->find($id);

        return null === $station ? null : self::ref($station);
    }

    public function nearest(Point $point, int $maxMeters): ?PoiRef
    {
        $found = $this->stations->findWithinRadius($point, $maxMeters, 1);

        return [] === $found ? null : self::ref($found[0]);
    }

    public function nearby(Point $point, int $radiusMeters, int $limit, ?Uuid $exclude = null): array
    {
        return array_map(self::ref(...), $this->stations->findWithinRadius($point, $radiusMeters, $limit, $exclude));
    }

    public static function ref(FuelStation $station): PoiRef
    {
        $name = null !== $station->getBrand() && !str_contains(mb_strtolower($station->getName()), mb_strtolower($station->getBrand()))
            ? $station->getBrand().' '.$station->getName()
            : $station->getName();

        return new PoiRef(PoiKind::FuelStation, $station->getId(), $name, $station->getLocation());
    }
}
