<?php

declare(strict_types=1);

namespace App\Shared\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

/**
 * Any PostGIS geometry (polygon, multipolygon, ...) as a decoded GeoJSON array.
 */
final class GeoGeometryType extends Type
{
    public const string NAME = 'geo_geometry';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'geometry(Geometry,4326)';
    }

    /** @return array<string, mixed>|null */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        if (null === $value) {
            return null;
        }
        if (\is_array($value)) {
            return $value;
        }
        if (!\is_string($value)) {
            throw InvalidType::new($value, self::NAME, ['string', 'null']);
        }

        /** @var array<string, mixed> */
        return json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!\is_array($value)) {
            throw InvalidType::new($value, self::NAME, ['array', 'null']);
        }

        return json_encode($value, \JSON_THROW_ON_ERROR);
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
