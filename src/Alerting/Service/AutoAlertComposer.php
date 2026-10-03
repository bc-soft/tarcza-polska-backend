<?php

declare(strict_types=1);

namespace App\Alerting\Service;

use App\Alerting\Dto\CreateAlertRequest;
use App\Alerting\Entity\Alert;
use App\Incident\Entity\Incident;
use App\Incident\Enum\ConfidenceLevel;
use App\Reporting\Enum\ReportType;

/**
 * Builds the automatic geographic alert sent when an incident reaches the configured confidence level.
 * Pure text composition, kept separate so the wording can be unit-tested and tuned without touching the handler.
 */
final class AutoAlertComposer
{
    public const string SYSTEM_AUTHOR = 'system';
    public const int TTL_MINUTES = 180;

    public function compose(Incident $incident): CreateAlertRequest
    {
        $t = $incident->tallies();
        $answered = $t['yes'] + $t['no'];
        $pct = $answered > 0 ? (int) round(100 * $t['yes'] / $answered) : null;
        $type = $incident->getType();
        $confirmed = $incident->getConfidenceLevel()->atLeast(ConfidenceLevel::Confirmed);

        $title = \sprintf('%s: %s w Twojej okolicy', $confirmed ? 'Potwierdzono' : 'Prawdopodobnie', mb_strtolower($type->label()));

        $community = null === $pct
            ? \sprintf('Problem zgłosiło %d osób.', $t['reports'])
            : \sprintf('Problem potwierdza %d%% odpowiadających użytkowników.', $pct);

        return new CreateAlertRequest(
            title: $title,
            body: $community.' '.self::advice($type),
            severity: $confirmed ? Alert::SEVERITY_WARNING : Alert::SEVERITY_INFO,
            incidentId: $incident->getId()->toRfc4122(),
            area: null,
            ttlMinutes: self::TTL_MINUTES,
        );
    }

    public static function advice(ReportType $type): string
    {
        return match ($type) {
            ReportType::PowerOutage => 'Odłącz wrażliwe urządzenia i oszczędzaj baterię telefonu. Procedura „Brak prądu” w aplikacji.',
            ReportType::WaterOutage => 'Zakręć krany i racjonuj zapasy wody. Procedura „Brak wody” w aplikacji.',
            ReportType::FuelShortage => 'Nie tankuj na zapas, sprawdź w aplikacji działające stacje.',
            ReportType::RoadBlocked => 'Wybierz objazd wskazany przez służby, nie wjeżdżaj w wodę ani pod zerwane przewody.',
            ReportType::ShelterIssue => 'Sprawdź w aplikacji najbliższy dostępny schron.',
            ReportType::OtherThreat => 'Oddal się od zagrożenia i śledź komunikaty służb. Najbliższy schron znajdziesz w aplikacji.',
        };
    }
}
