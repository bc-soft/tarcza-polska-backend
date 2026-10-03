<?php

declare(strict_types=1);

namespace App\Shared\Geo;

use Doctrine\DBAL\Connection;
use InvalidArgumentException;

/**
 * Thin wrapper over the h3-pg PostgreSQL extension.
 * All hexagonal-grid math is done in SQL so PHP never needs an H3 binding.
 */
final readonly class H3
{
    public function __construct(
        private Connection $connection,
        private int $h3Resolution,
    ) {
    }

    public function resolution(): int
    {
        return $this->h3Resolution;
    }

    public function cellFor(Point $point, ?int $resolution = null): string
    {
        /** @var string $cell */
        $cell = $this->connection->fetchOne(
            'SELECT h3_lat_lng_to_cell(POINT(:lng, :lat), :res)::text',
            ['lng' => $point->lng, 'lat' => $point->lat, 'res' => $resolution ?? $this->h3Resolution],
        );

        return $cell;
    }

    public function center(string $cell): Point
    {
        /** @var array{lng: float|string, lat: float|string} $row */
        $row = $this->connection->fetchAssociative(
            'SELECT (h3_cell_to_lat_lng(:cell::h3index))[0] AS lng, (h3_cell_to_lat_lng(:cell::h3index))[1] AS lat',
            ['cell' => $cell],
        );

        return new Point((float) $row['lat'], (float) $row['lng']);
    }

    /** @return list<string> all cells within distance k (including the origin) */
    public function gridDisk(string $cell, int $k): array
    {
        /** @var list<string> */
        return $this->connection->fetchFirstColumn(
            'SELECT h3_grid_disk(:cell::h3index, :k)::text',
            ['cell' => $cell, 'k' => $k],
        );
    }

    /** @return list<string> hollow ring at exactly distance k */
    public function gridRing(string $cell, int $k): array
    {
        /** @var list<string> */
        return $this->connection->fetchFirstColumn(
            'SELECT h3_grid_ring_unsafe(:cell::h3index, :k)::text',
            ['cell' => $cell, 'k' => $k],
        );
    }

    /**
     * Neighbours (distance 1) of a set of cells, excluding the set itself.
     *
     * @param list<string> $cells
     *
     * @return list<string>
     */
    public function frontier(array $cells): array
    {
        if ([] === $cells) {
            return [];
        }

        /** @var list<string> */
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT n::text FROM unnest(:cells::h3index[]) AS c, h3_grid_ring_unsafe(c, 1) AS n WHERE NOT (n = ANY(:cells::h3index[]))',
            ['cells' => self::pgArray($cells)],
        );
    }

    /**
     * Union of hexagons as a GeoJSON MultiPolygon.
     *
     * With $fillHoles (default) interior rings are dropped: a cell nobody could answer for, surrounded by
     * confirmed cells, is treated as part of the incident area (alerts and the public map stay contiguous).
     *
     * @param list<string> $cells
     *
     * @return array<string, mixed>|null
     */
    public function cellsToMultiPolygon(array $cells, bool $fillHoles = true): ?array
    {
        if ([] === $cells) {
            return null;
        }

        /** @var string|false $json */
        $json = $this->connection->fetchOne(
            'SELECT ST_AsGeoJSON(h3_cells_to_multi_polygon_geometry(:cells::h3index[]))',
            ['cells' => self::pgArray($cells)],
        );

        if (false === $json || '' === $json) {
            return null;
        }

        /** @var array{type: string, coordinates: list<list<list<array{0: float, 1: float}>>>} $geometry */
        $geometry = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        if ($fillHoles && 'MultiPolygon' === $geometry['type']) {
            $geometry['coordinates'] = array_map(static fn (array $polygon) => [$polygon[0]], $geometry['coordinates']);
        }

        return $geometry;
    }

    /** @return array<string, mixed> GeoJSON Polygon of a single cell */
    public function cellBoundary(string $cell): array
    {
        /** @var string $json */
        $json = $this->connection->fetchOne(
            'SELECT ST_AsGeoJSON(h3_cell_to_boundary_geometry(:cell::h3index))',
            ['cell' => $cell],
        );

        /** @var array<string, mixed> */
        return json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * PostgreSQL array literal for a list of H3 indexes (DBAL expands array params to "?, ?", not to a native array).
     *
     * @param list<string> $cells
     */
    public static function pgArray(array $cells): string
    {
        foreach ($cells as $cell) {
            if (1 !== preg_match('/^[0-9a-f]{15,16}$/', $cell)) {
                throw new InvalidArgumentException(\sprintf('Invalid H3 index "%s"', $cell));
            }
        }

        return '{'.implode(',', $cells).'}';
    }

    public function distance(string $a, string $b): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT h3_grid_distance(:a::h3index, :b::h3index)',
            ['a' => $a, 'b' => $b],
        );
    }
}
