<?php

declare(strict_types=1);

namespace App\Shared\Poi;

use App\Shared\Geo\Point;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Uid\Uuid;

/**
 * Lets Reporting / Verification talk about fuel stations and shelters without depending on those modules.
 * Each POI module provides one implementation; PoiRegistry picks it by kind.
 */
#[AutoconfigureTag(self::TAG)]
interface PoiLocatorInterface
{
    public const string TAG = 'app.poi_locator';

    public function kind(): PoiKind;

    public function find(Uuid $id): ?PoiRef;

    public function nearest(Point $point, int $maxMeters): ?PoiRef;

    /** @return list<PoiRef> nearest first, excluding $exclude */
    public function nearby(Point $point, int $radiusMeters, int $limit, ?Uuid $exclude = null): array;
}
