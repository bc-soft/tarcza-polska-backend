<?php

declare(strict_types=1);

namespace App\Fuel\Exception;

use RuntimeException;

/**
 * Every configured Overpass endpoint failed; the message lists each attempt so the operator can pick a mirror.
 */
final class OverpassUnavailableException extends RuntimeException
{
    /** @param array<string, string> $failures endpoint => reason */
    public static function fromFailures(array $failures): self
    {
        $lines = [];
        foreach ($failures as $endpoint => $reason) {
            $lines[] = \sprintf(' - %s: %s', $endpoint, $reason);
        }

        return new self(\sprintf(
            "No Overpass endpoint answered (%d tried):\n%s\nSet OVERPASS_URL in .env.local to a comma-separated list of mirrors that work from your network.",
            \count($failures),
            implode("\n", $lines),
        ));
    }
}
