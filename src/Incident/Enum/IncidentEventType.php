<?php

declare(strict_types=1);

namespace App\Incident\Enum;

/**
 * Entries of an incident's persistent history (post-MVP "historia incydentu").
 */
enum IncidentEventType: string
{
    case Created = 'created';
    case ReportAttached = 'report_attached';
    case WaveStarted = 'wave_started';
    case WaveClosed = 'wave_closed';
    case AreaChanged = 'area_changed';
    case ConfidenceChanged = 'confidence_changed';
    case ResearchCompleted = 'research_completed';
    case SourceAdded = 'source_added';
    case AlertPublished = 'alert_published';
    case PhotoAttached = 'photo_attached';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Wykryto skupisko zgłoszeń',
            self::ReportAttached => 'Nowe zgłoszenie dołączyło do incydentu',
            self::WaveStarted => 'Wysłano falę pytań weryfikacyjnych',
            self::WaveClosed => 'Fala pytań zakończona',
            self::AreaChanged => 'Zmienił się zasięg incydentu',
            self::ConfidenceChanged => 'Zmienił się poziom wiarygodności',
            self::ResearchCompleted => 'Zakończono research w źródłach publicznych',
            self::SourceAdded => 'Dodano źródło zewnętrzne',
            self::AlertPublished => 'Wysłano komunikat do obszaru',
            self::PhotoAttached => 'Dołączono zdjęcie do zgłoszenia',
            self::Resolved => 'Incydent zamknięty',
        };
    }

    /** Entries citizens may see (no raw positions, no operator internals). */
    public function isPublic(): bool
    {
        return self::ReportAttached !== $this;
    }
}
