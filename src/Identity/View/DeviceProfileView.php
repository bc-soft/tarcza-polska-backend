<?php

declare(strict_types=1);

namespace App\Identity\View;

use App\Identity\Entity\Device;

/** GET /api/v1/devices/me (OpenAPI: DeviceProfile). */
final class DeviceProfileView
{
    /** @return array<string, mixed> */
    public static function toArray(Device $device): array
    {
        return [
            'deviceId' => $device->getId()->toRfc4122(),
            'platform' => $device->getPlatform(),
            'hasPushToken' => null !== $device->getPushToken(),
            'lastLocation' => $device->getLastLocation()?->toGeoJson(),
            'h3Cell' => $device->getH3Cell(),
            'locationUpdatedAt' => $device->getLocationUpdatedAt()?->format(\DATE_ATOM),
            'locationSource' => $device->getLocationSource()?->value,
            'preferences' => ['locationRefresh' => $device->isLocationRefreshEnabled()],
        ];
    }
}
