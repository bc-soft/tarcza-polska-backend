<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Geo\BoundingBox;
use App\Shared\Geo\Point;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PointTest extends TestCase
{
    #[Test]
    public function geoJsonRoundTripUsesLngLatOrder(): void
    {
        $p = new Point(52.4121, 16.9012);

        self::assertSame(['type' => 'Point', 'coordinates' => [16.9012, 52.4121]], $p->toGeoJson());
        self::assertEquals($p, Point::fromGeoJson($p->toGeoJson()));
    }

    #[Test]
    public function haversineDistanceIsRoughlyRight(): void
    {
        $jezyce = new Point(52.4121, 16.9012);
        $staryRynek = new Point(52.4082, 16.9335);

        self::assertEqualsWithDelta(2240, $jezyce->distanceTo($staryRynek), 60);
    }

    #[Test]
    public function rejectsCoordinatesOutOfRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Point(91, 0);
    }

    #[Test]
    public function boundingBoxParsesQueryParameter(): void
    {
        $bbox = BoundingBox::fromString('16.8,52.3,17.1,52.5');

        self::assertSame(16.8, $bbox->minLng);
        self::assertSame(52.5, $bbox->maxLat);
        self::assertSame(['bbox_min_lng' => 16.8, 'bbox_min_lat' => 52.3, 'bbox_max_lng' => 17.1, 'bbox_max_lat' => 52.5], $bbox->params());
    }
}
