<?php

declare(strict_types=1);

namespace App\Fuel\Controller;

use App\Fuel\Dto\ConfirmFuelStatusRequest;
use App\Fuel\Entity\FuelStation;
use App\Fuel\Enum\FuelAvailability;
use App\Fuel\Enum\FuelType;
use App\Fuel\Repository\FuelStationRepository;
use App\Fuel\View\FuelStationView;
use App\Identity\Entity\Device;
use App\Shared\Api\ApiProblemException;
use App\Shared\Api\ConditionalJsonResponse;
use App\Shared\Geo\BoundingBox;
use App\Shared\Geo\Point;
use App\Shared\Geo\Region;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/fuel-stations')]
#[OA\Tag(name: 'Fuel stations')]
final class FuelStationController
{
    public function __construct(
        private readonly FuelStationRepository $stations,
        private readonly EntityManagerInterface $em,
        private readonly Region $region,
    ) {
    }

    #[Route('', name: 'api_fuel_station_list', methods: ['GET'])]
    #[OA\Get(summary: 'Fuel stations in a bbox, or the nearest ones to lat/lng, with per-fuel availability')]
    #[OA\Parameter(name: 'bbox', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'lat', in: 'query', schema: new OA\Schema(type: 'number'))]
    #[OA\Parameter(name: 'lng', in: 'query', schema: new OA\Schema(type: 'number'))]
    #[OA\Response(response: 200, description: 'Stations (distanceMeters only with lat/lng; supports ETag / 304)', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/FuelStationView')))]
    #[OA\Response(response: 400, description: 'Malformed bbox or coordinates (error.code: bad_request)')]
    public function list(Request $request): JsonResponse
    {
        try {
            if ($request->query->has('lat') && $request->query->has('lng')) {
                $point = new Point((float) $request->query->get('lat'), (float) $request->query->get('lng'));

                return ConditionalJsonResponse::create($request, array_map(
                    static fn (FuelStation $s) => FuelStationView::toArray($s, $s->getLocation()->distanceTo($point)),
                    $this->stations->findNearest($point, 15),
                ));
            }
            $bbox = $request->query->has('bbox') ? BoundingBox::fromString((string) $request->query->get('bbox')) : $this->region->boundingBox();
        } catch (InvalidArgumentException $e) {
            throw new ApiProblemException(400, 'bad_request', $e->getMessage());
        }

        return ConditionalJsonResponse::create($request, array_map(FuelStationView::toArray(...), $this->stations->findInBoundingBox($bbox)));
    }

    #[Route('/{id}', name: 'api_fuel_station_show', methods: ['GET'])]
    #[OA\Get(summary: 'Fuel station details')]
    #[OA\Response(response: 200, description: 'Station', content: new OA\JsonContent(ref: '#/components/schemas/FuelStationView'))]
    public function show(FuelStation $station): JsonResponse
    {
        return new JsonResponse(FuelStationView::toArray($station));
    }

    #[Route('/{id}/status', name: 'api_fuel_station_confirm', methods: ['POST'])]
    #[OA\Post(summary: 'Confirm fuel availability at a station I am standing at ("there is / there is no pb95 and diesel")')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: ConfirmFuelStatusRequest::class)))]
    #[OA\Response(response: 200, description: 'Updated station', content: new OA\JsonContent(ref: '#/components/schemas/FuelStationView'))]
    public function confirm(#[CurrentUser] Device $device, FuelStation $station, #[MapRequestPayload] ConfirmFuelStatusRequest $request): JsonResponse
    {
        $types = array_values(array_filter($request->fuelTypes, static fn ($t) => $t instanceof FuelType));
        if ([] === $types) {
            throw new ApiProblemException(422, 'validation_failed', 'fuelTypes must contain pb95, pb98, diesel or lpg');
        }
        $station->confirmAvailability($types, $request->available ? FuelAvailability::Available : FuelAvailability::Unavailable);
        $device->touch();
        $this->em->flush();

        return new JsonResponse(FuelStationView::toArray($station));
    }
}
