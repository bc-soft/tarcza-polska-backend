<?php

declare(strict_types=1);

namespace App\Fuel\Enum;

enum FuelType: string
{
    case Pb95 = 'pb95';
    case Pb98 = 'pb98';
    case Diesel = 'diesel';
    case Lpg = 'lpg';

    public function label(): string
    {
        return match ($this) {
            self::Pb95 => 'Benzyna 95',
            self::Pb98 => 'Benzyna 98',
            self::Diesel => 'Olej napędowy',
            self::Lpg => 'LPG',
        };
    }

    /** OSM tags (fuel:*=yes) that mean this type is sold at a station. */
    public function osmTag(): string
    {
        return match ($this) {
            self::Pb95 => 'fuel:octane_95',
            self::Pb98 => 'fuel:octane_98',
            self::Diesel => 'fuel:diesel',
            self::Lpg => 'fuel:lpg',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $t) => $t->value, self::cases());
    }

    /**
     * @param list<string> $values
     *
     * @return list<self>
     */
    public static function fromValues(array $values): array
    {
        $out = [];
        foreach ($values as $v) {
            $t = self::tryFrom($v);
            if (null !== $t && !\in_array($t, $out, true)) {
                $out[] = $t;
            }
        }

        return $out;
    }

    /** @param list<self> $types */
    public static function labels(array $types): string
    {
        return implode(', ', array_map(static fn (self $t) => $t->label(), $types));
    }
}
