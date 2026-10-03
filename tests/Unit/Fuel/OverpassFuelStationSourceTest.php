<?php

declare(strict_types=1);

namespace App\Tests\Unit\Fuel;

use App\Fuel\Entity\FuelStation;
use App\Fuel\Enum\FuelAvailability;
use App\Fuel\Enum\FuelType;
use App\Fuel\Exception\OverpassUnavailableException;
use App\Fuel\Service\OverpassFuelStationSource;
use App\Shared\Geo\Point;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OverpassFuelStationSourceTest extends TestCase
{
    private const string OVERPASS = <<<'JSON'
        {"version":0.6,"elements":[
          {"type":"node","id":101,"lat":52.4125,"lon":16.9020,"tags":{"amenity":"fuel","name":"Orlen Jeżyce","brand":"Orlen","fuel:diesel":"yes","fuel:octane_95":"yes","fuel:lpg":"no","addr:street":"Dąbrowskiego","addr:housenumber":"12","addr:city":"Poznań"}},
          {"type":"way","id":202,"center":{"lat":52.4200,"lon":16.9100},"tags":{"amenity":"fuel","operator":"BP","fuel:octane_98":"yes"}},
          {"type":"node","id":303,"tags":{"amenity":"fuel","name":"bez współrzędnych"}},
          {"type":"node","id":404,"lat":52.43,"lon":16.92,"tags":{"amenity":"fuel"}}
        ]}
        JSON;

    #[Test]
    public function parsesNodesAndWaysWithBrandAddressAndFuels(): void
    {
        $stations = OverpassFuelStationSource::parse(self::OVERPASS);

        self::assertCount(3, $stations, 'the element without coordinates is skipped');
        self::assertSame('node/101', $stations[0]->externalId);
        self::assertSame('Orlen Jeżyce', $stations[0]->name);
        self::assertSame('Orlen', $stations[0]->brand);
        self::assertSame('Dąbrowskiego 12, Poznań', $stations[0]->address);
        self::assertSame([FuelType::Pb95, FuelType::Diesel], $stations[0]->fuelTypes);
        self::assertEqualsWithDelta(52.4125, $stations[0]->location->lat, 1e-6);

        self::assertSame('way/202', $stations[1]->externalId);
        self::assertSame('BP', $stations[1]->name, 'operator is the fallback name');
        self::assertSame([FuelType::Pb98], $stations[1]->fuelTypes);
        self::assertNull($stations[1]->address);

        self::assertSame('Stacja paliw', $stations[2]->name);
        self::assertSame([], $stations[2]->fuelTypes);
    }

    #[Test]
    public function stationTracksAvailabilityPerFuelType(): void
    {
        $station = new FuelStation('Test', new Point(52.4, 16.9), '891e24aa0b3ffff', 'fixture');
        $station->setFuelTypes([FuelType::Pb95, FuelType::Diesel]);

        self::assertFalse($station->hasShortage());
        self::assertSame(FuelAvailability::Unknown, $station->availabilityOf(FuelType::Diesel));

        $station->confirmAvailability([FuelType::Diesel], FuelAvailability::Unavailable);

        self::assertTrue($station->hasShortage());
        self::assertSame([FuelType::Diesel], $station->missingFuelTypes());
        self::assertSame(FuelAvailability::Unknown, $station->availabilityOf(FuelType::Pb95));
        self::assertSame(1, $station->getConfirmationCount());
        self::assertNotNull($station->availabilityConfirmedAt(FuelType::Diesel));

        $station->confirmAvailability([FuelType::Diesel], FuelAvailability::Available);
        self::assertFalse($station->hasShortage());
    }

    #[Test]
    public function fuelTypeHelpers(): void
    {
        self::assertSame([FuelType::Pb95, FuelType::Lpg], FuelType::fromValues(['pb95', 'nope', 'lpg', 'pb95']));
        self::assertSame('Benzyna 95, LPG', FuelType::labels([FuelType::Pb95, FuelType::Lpg]));
        self::assertSame(['pb95', 'pb98', 'diesel', 'lpg'], FuelType::values());
    }

    #[Test]
    public function fallsBackToTheNextMirrorWhenTheFirstOneFails(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', ['error' => 'Idle timeout reached for "https://overpass-api.de/api/interpreter".']), // probe #1
            new MockResponse('Gateway Timeout', ['http_code' => 504]), // probe #2
            new MockResponse('{"elements":[]}'), // probe #3
            new MockResponse(self::OVERPASS), // query #3
        ]);
        $source = new OverpassFuelStationSource(
            $client,
            'https://overpass-api.de/api/interpreter, https://second.example/api/interpreter,https://third.example/api/interpreter',
        );

        self::assertCount(3, $source->endpoints());
        $stations = $source->fetchAround(new Point(52.41, 16.9), 15000);

        self::assertCount(3, $stations);
        self::assertSame(4, $client->getRequestsCount(), 'two failing mirrors were skipped after one probe each');
    }

    #[Test]
    public function reportsEveryMirrorWhenAllFail(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', ['error' => 'Connection refused']),
            new MockResponse('Too Many Requests', ['http_code' => 429]),
        ]);
        $source = new OverpassFuelStationSource($client, 'https://a.example/api,https://b.example/api');

        $this->expectException(OverpassUnavailableException::class);
        $this->expectExceptionMessageMatches('/2 tried.*a\.example.*b\.example/s');
        $source->fetchAround(new Point(52.41, 16.9), 15000);
    }
}
