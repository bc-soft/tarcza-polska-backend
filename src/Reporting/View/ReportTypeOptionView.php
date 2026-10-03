<?php

declare(strict_types=1);

namespace App\Reporting\View;

use App\Fuel\Enum\FuelType;
use App\Reporting\Enum\ReportType;

/** One entry of GET /api/v1/reports/types: what the report screen needs to render a category. */
final class ReportTypeOptionView
{
    /** @return array<string, mixed> */
    public static function toArray(ReportType $type): array
    {
        return [
            'value' => $type->value,
            'label' => $type->label(),
            'scope' => $type->scope()->value,
            'poiKind' => $type->poiKind()?->value,
            'fuelTypes' => ReportType::FuelShortage === $type
                ? array_map(static fn (FuelType $f) => ['value' => $f->value, 'label' => $f->label()], FuelType::cases())
                : [],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return array_map(self::toArray(...), ReportType::cases());
    }
}
