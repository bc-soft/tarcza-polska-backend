<?php

declare(strict_types=1);

namespace App\Shared\Doctrine\Type;

use App\Shared\Geo\Point;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

/**
 * Maps App\Shared\Geo\Point <-> PostGIS geometry (SRID 4326) through GeoJSON.
 * The declaration is intentionally identical to GeoGeometryType so that schema diffs stay clean.
 */
final class GeoPointType extends Type
{
    public const string NAME = 'geo_point';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'geometry(Geometry,4326)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Point
    {
        if (null === $value || $value instanceof Point) {
            return $value;
        }
        if (!\is_string($value)) {
            throw InvalidType::new($value, self::NAME, ['string', 'null']);
        }
        /** @var array{type?: string, coordinates: array{0: float, 1: float}} $decoded */
        $decoded = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);

        return Point::fromGeoJson($decoded);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!$value instanceof Point) {
            throw InvalidType::new($value, self::NAME, [Point::class, 'null']);
        }

        return json_encode($value->toGeoJson(), \JSON_THROW_ON_ERROR);
    }

    public function convertToDatabaseValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return \sprintf('ST_SetSRID(ST_GeomFromGeoJSON(%s), 4326)', $sqlExpr);
    }

    public function convertToPHPValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return \sprintf('ST_AsGeoJSON(%s)', $sqlExpr);
    }

    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return ['geometry'];
    }
}
