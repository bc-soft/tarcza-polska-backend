<?php

declare(strict_types=1);

namespace App\Simulation\Service;

use App\Shared\Geo\Point;

final class GeoRandom
{
    /** Uniform random point within $radiusMeters of $center. */
    public static function around(Point $center, float $radiusMeters): Point
    {
        $r = $radiusMeters * sqrt(mt_rand() / mt_getrandmax());
        $theta = 2 * \M_PI * (mt_rand() / mt_getrandmax());
        $dLat = ($r * cos($theta)) / 111_320.0;
        $dLng = ($r * sin($theta)) / (111_320.0 * cos(deg2rad($center->lat)));

        return new Point($center->lat + $dLat, $center->lng + $dLng);
    }
}
