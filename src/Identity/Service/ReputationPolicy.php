<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Incident\Enum\CellState;
use App\Incident\Enum\IncidentResolution;

/**
 * Deterministic reputation rules (post-MVP "user reputation"). Pure functions, unit-tested.
 * Reputation lives on Device in [0, 2] and multiplies the weight of future reports (1.0 = neutral).
 */
final class ReputationPolicy
{
    public const float REPORTER_CONFIRMED = 0.10;
    public const float REPORTER_FALSE_ALARM = -0.25;
    public const float ANSWER_CONSISTENT = 0.02;
    public const float ANSWER_INCONSISTENT = -0.05;

    /** Delta for a device that filed a report on an incident closed with $resolution. */
    public function reporterDelta(IncidentResolution $resolution): float
    {
        return match ($resolution) {
            IncidentResolution::Confirmed => self::REPORTER_CONFIRMED,
            IncidentResolution::FalseAlarm => self::REPORTER_FALSE_ALARM,
            IncidentResolution::Expired => 0.0,
        };
    }

    /**
     * Delta for a verification answer compared with the final verdict of its cell.
     *
     * @param 'yes'|'no'|'unknown'|null $normalisedAnswer "yes" = the device said the problem was present
     */
    public function answerDelta(?string $normalisedAnswer, CellState $finalCellState, IncidentResolution $resolution): float
    {
        if (IncidentResolution::Expired === $resolution || null === $normalisedAnswer || 'unknown' === $normalisedAnswer) {
            return 0.0;
        }
        if (CellState::Unknown === $finalCellState) {
            return 0.0; // nobody else answered there, nothing to compare against
        }

        $saidPresent = 'yes' === $normalisedAnswer;
        $wasPresent = CellState::Positive === $finalCellState;
        if (IncidentResolution::FalseAlarm === $resolution) {
            $wasPresent = false; // the operator overruled the crowd: the problem was not real
        }

        return $saidPresent === $wasPresent ? self::ANSWER_CONSISTENT : self::ANSWER_INCONSISTENT;
    }
}
