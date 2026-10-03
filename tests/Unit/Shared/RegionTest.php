<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Geo\Point;
use App\Shared\Geo\Region;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RegionTest extends TestCase
{
    #[Test]
    public function boundingBoxAndContainmentFollowTheRadius(): void
    {
        $poznan = new Region('Poznań', 52.4064, 16.9252, 12);

        self::assertSame(12000, $poznan->radiusMeters());
        self::assertTrue($poznan->contains(new Point(52.4121, 16.9012)), 'Jeżyce');
        self::assertTrue($poznan->contains(new Point(52.4664, 16.9268)), 'Morasko');
        self::assertFalse($poznan->contains(new Point(52.2297, 21.0122)), 'Warszawa');

        $box = $poznan->boundingBox();
        self::assertLessThan(16.9252, $box->minLng);
        self::assertGreaterThan(16.9252, $box->maxLng);
        self::assertEqualsWithDelta(0.216, $box->maxLat - $box->minLat, 0.01, '24 km tall');
    }
}
