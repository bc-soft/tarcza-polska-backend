<?php

declare(strict_types=1);

namespace App\Verification\Enum;

enum VerificationAnswer: string
{
    case Yes = 'yes';
    case No = 'no';
    case Unknown = 'unknown';

    /**
     * Normalise to "problem present here?" given how the question was phrased.
     *
     * @return 'yes'|'no'|'unknown'
     */
    public function normalise(bool $yesMeansProblemPresent): string
    {
        return match ($this) {
            self::Unknown => 'unknown',
            self::Yes => $yesMeansProblemPresent ? 'yes' : 'no',
            self::No => $yesMeansProblemPresent ? 'no' : 'yes',
        };
    }
}
