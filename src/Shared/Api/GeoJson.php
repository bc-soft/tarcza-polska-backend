<?php

declare(strict_types=1);

namespace App\Shared\Api;

/**
 * Helpers for building GeoJSON Feature / FeatureCollection payloads.
 */
final class GeoJson
{
    /**
     * @param array<string, mixed>|null $geometry
     * @param array<string, mixed>      $properties
     *
     * @return array<string, mixed>
     */
    public static function feature(?array $geometry, array $properties, string|int|null $id = null): array
    {
        $feature = ['type' => 'Feature', 'geometry' => $geometry, 'properties' => $properties];
        if (null !== $id) {
            $feature['id'] = $id;
        }

        return $feature;
    }

    /**
     * @param list<array<string, mixed>> $features
     *
     * @return array{type: 'FeatureCollection', features: list<array<string, mixed>>}
     */
    public static function collection(array $features): array
    {
        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
