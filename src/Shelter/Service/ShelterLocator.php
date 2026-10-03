<?php

declare(strict_types=1);

namespace App\Shelter\Service;

use App\Shared\Geo\Point;
use App\Shared\Poi\PoiKind;
use App\Shared\Poi\PoiLocatorInterface;
use App\Shared\Poi\PoiRef;
use App\Shelter\Entity\Shelter;
use App\Shelter\Repository\ShelterRepository;
use Symfony\Component\Uid\Uuid;

final readonly class ShelterLocator implements PoiLocatorInterface
{
    public function __construct(private ShelterRepository $shelters)
    {
    }

    public function kind(): PoiKind
    {
        return PoiKind::Shelter;
    }

    public function find(Uuid $id): ?PoiRef
    {
        $shelter = $this->shelters->find($id);

        return null === $shelter ? null : self::ref($shelter);
    }

    public function nearest(Point $point, int $maxMeters): ?PoiRef
    {
        $found = $this->shelters->findWithinRadius($point, $maxMeters, 1);

        return [] === $found ? null : self::ref($found[0]);
    }

    public function nearby(Point $point, int $radiusMeters, int $limit, ?Uuid $exclude = null): array
    {
        return array_map(self::ref(...), $this->shelters->findWithinRadius($point, $radiusMeters, $limit, $exclude));
    }

    public static function ref(Shelter $shelter): PoiRef
    {
        $name = $shelter->getName();
        if (null !== $shelter->getAddress() && !str_contains($name, $shelter->getAddress())) {
            $name .= ' · '.$shelter->getAddress();
        }

        return new PoiRef(PoiKind::Shelter, $shelter->getId(), $name, $shelter->getLocation());
    }
}
