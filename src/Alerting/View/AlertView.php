<?php

declare(strict_types=1);

namespace App\Alerting\View;

use App\Alerting\Entity\Alert;
use App\Shared\Api\GeoJson;

final class AlertView
{
    /** @return array<string, mixed> */
    public static function toArray(Alert $alert, bool $withArea = true): array
    {
        $out = [
            'id' => $alert->getId()->toRfc4122(),
            'title' => $alert->getTitle(),
            'body' => $alert->getBody(),
            'severity' => $alert->getSeverity(),
            'incidentId' => $alert->getIncident()?->getId()->toRfc4122(),
            'createdAt' => $alert->getCreatedAt()->format(\DATE_ATOM),
            'expiresAt' => $alert->getExpiresAt()->format(\DATE_ATOM),
            'active' => $alert->isActive(),
        ];
        if ($withArea) {
            $out['area'] = $alert->getArea();
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public static function toFeature(Alert $alert): array
    {
        return GeoJson::feature($alert->getArea(), ['kind' => 'alert'] + self::toArray($alert, false), $alert->getId()->toRfc4122());
    }
}
