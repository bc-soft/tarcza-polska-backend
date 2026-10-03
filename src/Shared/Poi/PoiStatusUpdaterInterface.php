<?php

declare(strict_types=1);

namespace App\Shared\Poi;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Uid\Uuid;

/**
 * Applies a crowd answer about a POI to the POI's own status (fuel availability, shelter accessibility).
 */
#[AutoconfigureTag(self::TAG)]
interface PoiStatusUpdaterInterface
{
    public const string TAG = 'app.poi_status_updater';

    public function kind(): PoiKind;

    /**
     * @param bool         $problemPresent normalised crowd answer: true = the reported problem exists at this POI
     * @param list<string> $context        type-specific detail, e.g. fuel types the question was about
     */
    public function applyAnswer(Uuid $poiId, bool $problemPresent, array $context = []): void;
}
