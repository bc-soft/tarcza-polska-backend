<?php

declare(strict_types=1);

namespace App\Shelter\View;

use App\Shared\Api\GeoJson;
use App\Shelter\Entity\Shelter;

final class ShelterView
{
    /** @return array<string, mixed> */
    public static function toArray(Shelter $s, ?float $distanceMeters = null): array
    {
        $out = [
            'id' => $s->getId()->toRfc4122(),
            'name' => $s->getName(),
            'address' => $s->getAddress(),
            'location' => $s->getLocation()->toGeoJson(),
            'capacity' => $s->getCapacity(),
            'status' => $s->getStatus()->value,
            'statusLabel' => $s->getStatus()->label(),
            'lastConfirmedAt' => $s->getLastConfirmedAt()?->format(\DATE_ATOM),
            'confirmationCount' => $s->getConfirmationCount(),
        ];
        if (null !== $distanceMeters) {
            $out['distanceMeters'] = (int) round($distanceMeters);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public static function toFeature(Shelter $s): array
    {
        $props = self::toArray($s);
        unset($props['location']);

        return GeoJson::feature($s->getLocation()->toGeoJson(), ['kind' => 'shelter'] + $props, $s->getId()->toRfc4122());
    }
}
