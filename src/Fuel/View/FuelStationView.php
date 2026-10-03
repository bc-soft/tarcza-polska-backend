<?php

declare(strict_types=1);

namespace App\Fuel\View;

use App\Fuel\Entity\FuelStation;
use App\Fuel\Enum\FuelType;
use App\Shared\Api\GeoJson;

final class FuelStationView
{
    /** @return array<string, mixed> */
    public static function toArray(FuelStation $s, ?float $distanceMeters = null): array
    {
        $fuels = [];
        $tracked = $s->getFuelTypes();
        foreach (FuelType::cases() as $type) {
            $status = $s->availabilityOf($type);
            if ([] !== $tracked && !\in_array($type, $tracked, true) && 'unknown' === $status->value) {
                continue; // station does not sell it and nobody said anything about it
            }
            $fuels[] = [
                'type' => $type->value,
                'label' => $type->label(),
                'status' => $status->value,
                'statusLabel' => $status->label(),
                'confirmedAt' => $s->availabilityConfirmedAt($type)?->format(\DATE_ATOM),
            ];
        }

        $out = [
            'id' => $s->getId()->toRfc4122(),
            'name' => $s->getName(),
            'brand' => $s->getBrand(),
            'address' => $s->getAddress(),
            'location' => $s->getLocation()->toGeoJson(),
            'fuels' => $fuels,
            'shortage' => $s->hasShortage(),
            'missingFuelTypes' => array_map(static fn (FuelType $t) => $t->value, $s->missingFuelTypes()),
            'lastConfirmedAt' => $s->getLastConfirmedAt()?->format(\DATE_ATOM),
            'confirmationCount' => $s->getConfirmationCount(),
        ];
        if (null !== $distanceMeters) {
            $out['distanceMeters'] = (int) round($distanceMeters);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public static function toFeature(FuelStation $s): array
    {
        $props = self::toArray($s);
        unset($props['location']);

        return GeoJson::feature($s->getLocation()->toGeoJson(), ['kind' => 'fuel_station'] + $props, $s->getId()->toRfc4122());
    }
}
