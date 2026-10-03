<?php

declare(strict_types=1);

namespace App\Fuel\Service;

use App\Fuel\Enum\FuelType;
use App\Fuel\Exception\OverpassUnavailableException;
use App\Fuel\Model\FuelStationCandidate;
use App\Shared\Geo\BoundingBox;
use App\Shared\Geo\Point;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fuel stations from OpenStreetMap through the Overpass API (amenity=fuel, nodes and ways with their centre).
 *
 * OVERPASS_URL may list several mirrors separated by commas. The query is sent to all of them at once and the
 * first complete answer wins; the rest are cancelled. Public Overpass instances are regularly overloaded or
 * unreachable from some networks, and a busy one may queue a query for tens of seconds before answering.
 * The parser is separate from the HTTP call so it can be unit-tested with a fixture.
 */
final readonly class OverpassFuelStationSource
{
    public const string SOURCE = 'osm';

    /** Server-side query budget; Overpass sends nothing until the query finishes, so the idle timeout must exceed it. */
    private const int QUERY_TIMEOUT_SEC = 60;

    private HttpClientInterface $httpClient;

    /** @var list<string> */
    private array $endpoints;

    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        string $overpassUrl,
        ?LoggerInterface $logger = null,
    ) {
        $this->httpClient = $httpClient;
        $this->endpoints = array_values(array_filter(array_map('trim', explode(',', $overpassUrl)), static fn (string $u): bool => '' !== $u));
        if ([] === $this->endpoints) {
            throw new InvalidArgumentException('OVERPASS_URL must contain at least one endpoint');
        }
        $this->logger = $logger ?? new NullLogger();
    }

    /** @return list<FuelStationCandidate> */
    public function fetchAround(Point $center, int $radiusMeters): array
    {
        $query = \sprintf(
            '[out:json][timeout:'.self::QUERY_TIMEOUT_SEC.'];(node["amenity"="fuel"](around:%1$d,%2$F,%3$F);way["amenity"="fuel"](around:%1$d,%2$F,%3$F););out center tags;',
            $radiusMeters,
            $center->lat,
            $center->lng,
        );

        return $this->run($query);
    }

    /** @return list<FuelStationCandidate> */
    public function fetchInBoundingBox(BoundingBox $bbox): array
    {
        $box = \sprintf('%F,%F,%F,%F', $bbox->minLat, $bbox->minLng, $bbox->maxLat, $bbox->maxLng);
        $query = \sprintf('[out:json][timeout:'.self::QUERY_TIMEOUT_SEC.'];(node["amenity"="fuel"](%1$s);way["amenity"="fuel"](%1$s););out center tags;', $box);

        return $this->run($query);
    }

    /** @return list<string> */
    public function endpoints(): array
    {
        return $this->endpoints;
    }

    /**
     * @return list<FuelStationCandidate>
     *
     * @throws OverpassUnavailableException when no endpoint answers
     */
    private function run(string $query): array
    {
        $responses = [];
        foreach ($this->endpoints as $endpoint) {
            $responses[] = $this->httpClient->request('POST', $endpoint, [
                'body' => ['data' => $query],
                'headers' => ['User-Agent' => 'TarczaPolska/0.1 (+fuel-stations-import)'],
                'timeout' => self::QUERY_TIMEOUT_SEC + 15,
                'max_duration' => self::QUERY_TIMEOUT_SEC + 30,
                'user_data' => $endpoint,
            ]);
        }

        $failures = [];
        foreach ($this->httpClient->stream($responses) as $response => $chunk) {
            /** @var string $endpoint */
            $endpoint = $response->getInfo('user_data');
            try {
                if ($chunk->isFirst()) {
                    $response->getHeaders(); // throws on 3xx-5xx, so a busy mirror is dropped as soon as it says so
                }
                if (!$chunk->isLast()) {
                    continue;
                }
                $stations = self::parse($response->getContent());
            } catch (HttpClientException|JsonException|OverpassUnavailableException $e) {
                $failures[$endpoint] = $e->getMessage();
                $this->logger->warning('Overpass endpoint failed', ['endpoint' => $endpoint, 'reason' => $e->getMessage()]);
                $response->cancel();
                continue;
            }

            foreach ($responses as $other) {
                $other->cancel();
            }

            return $stations;
        }

        throw OverpassUnavailableException::fromFailures($failures);
    }

    /**
     * @return list<FuelStationCandidate>
     *
     * @throws JsonException
     * @throws OverpassUnavailableException when Overpass answered 200 but aborted the query (e.g. its own timeout)
     */
    public static function parse(string $json): array
    {
        /** @var array{elements?: list<array<string, mixed>>, remark?: string} $data */
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        if (isset($data['remark']) && str_contains($data['remark'], 'error') && [] === ($data['elements'] ?? [])) {
            throw new OverpassUnavailableException('Overpass aborted the query: '.$data['remark']);
        }
        $out = [];
        foreach ($data['elements'] ?? [] as $el) {
            $tags = \is_array($el['tags'] ?? null) ? $el['tags'] : [];
            $lat = $el['lat'] ?? ($el['center']['lat'] ?? null);
            $lng = $el['lon'] ?? ($el['center']['lon'] ?? null);
            if (!is_numeric($lat) || !is_numeric($lng)) {
                continue;
            }
            $brand = self::str($tags['brand'] ?? null) ?? self::str($tags['operator'] ?? null);
            $name = self::str($tags['name'] ?? null) ?? $brand ?? 'Stacja paliw';
            $address = self::address($tags);

            $fuels = [];
            foreach (FuelType::cases() as $type) {
                if ('yes' === ($tags[$type->osmTag()] ?? null)) {
                    $fuels[] = $type;
                }
            }

            $out[] = new FuelStationCandidate(
                externalId: \sprintf('%s/%s', (string) ($el['type'] ?? 'node'), (string) ($el['id'] ?? '')),
                name: $name,
                brand: $brand,
                address: $address,
                location: new Point((float) $lat, (float) $lng),
                fuelTypes: $fuels,
            );
        }

        return $out;
    }

    /** @param array<string, mixed> $tags */
    private static function address(array $tags): ?string
    {
        $street = self::str($tags['addr:street'] ?? null);
        $number = self::str($tags['addr:housenumber'] ?? null);
        $city = self::str($tags['addr:city'] ?? null);
        $line = trim(implode(' ', array_filter([$street, $number])));
        $parts = array_filter([$line, $city]);

        return [] === $parts ? null : implode(', ', $parts);
    }

    private static function str(mixed $v): ?string
    {
        return \is_string($v) && '' !== trim($v) ? trim($v) : null;
    }
}
