<?php

declare(strict_types=1);

namespace App\Intelligence\Service;

use App\Shared\Geo\Point;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Reverse geocoding for the research prompt ("Poznań, Jeżyce"). Nominatim with a polite UA; fails soft.
 */
final readonly class PlaceNamer
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function nameFor(Point $point): string
    {
        try {
            $response = $this->httpClient->request('GET', 'https://nominatim.openstreetmap.org/reverse', [
                'query' => ['format' => 'jsonv2', 'lat' => $point->lat, 'lon' => $point->lng, 'zoom' => 14, 'accept-language' => 'pl'],
                'headers' => ['User-Agent' => 'TarczaPolska/0.1 (hackathon prototype)'],
                'timeout' => 5,
            ]);
            /** @var array{address?: array<string, string>} $data */
            $data = $response->toArray();
            $a = $data['address'] ?? [];
            $parts = array_filter([
                $a['suburb'] ?? $a['neighbourhood'] ?? $a['quarter'] ?? null,
                $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? null,
            ]);
            if ([] !== $parts) {
                return implode(', ', $parts);
            }
        } catch (Throwable) {
            // fall through
        }

        return \sprintf('%.4f, %.4f', $point->lat, $point->lng);
    }
}
