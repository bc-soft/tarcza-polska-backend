<?php

declare(strict_types=1);

namespace App\Shelter\Controller;

use App\Identity\Entity\Device;
use App\Shared\Geo\BoundingBox;
use App\Shared\Geo\Point;
use App\Shared\Geo\Region;
use App\Shelter\Dto\ConfirmShelterStatusRequest;
use App\Shelter\Entity\Shelter;
use App\Shelter\Entity\ShelterStatusReport;
use App\Shelter\Enum\ShelterOccupancy;
use App\Shelter\Repository\ShelterRepository;
use App\Shelter\View\ShelterView;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/shelters')]
#[OA\Tag(name: 'Shelters')]
final class ShelterController
{
    public function __construct(
        private readonly ShelterRepository $shelters,
        private readonly EntityManagerInterface $em,
        private readonly Region $region,
    ) {
    }

    #[Route('', name: 'api_shelter_list', methods: ['GET'])]
    #[OA\Get(summary: 'Shelters in a bbox, or the nearest ones to lat/lng')]
    #[OA\Parameter(name: 'bbox', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'lat', in: 'query', schema: new OA\Schema(type: 'number'))]
    #[OA\Parameter(name: 'lng', in: 'query', schema: new OA\Schema(type: 'number'))]
    #[OA\Response(response: 200, description: 'Shelters (distanceMeters only with lat/lng)', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/ShelterView')))]
    #[OA\Response(response: 400, description: 'Malformed bbox (error.code: bad_request)')]
    public function list(Request $request): JsonResponse
    {
        if ($request->query->has('lat') && $request->query->has('lng')) {
            $point = new Point((float) $request->query->get('lat'), (float) $request->query->get('lng'));

            return new JsonResponse(array_map(
                static fn (Shelter $s) => ShelterView::toArray($s, $s->getLocation()->distanceTo($point)),
                $this->shelters->findNearest($point, 10),
            ));
        }

        try {
            $bbox = $request->query->has('bbox') ? BoundingBox::fromString((string) $request->query->get('bbox')) : $this->region->boundingBox();
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        return new JsonResponse(array_map(ShelterView::toArray(...), $this->shelters->findInBoundingBox($bbox)));
    }

    #[Route('/{id}', name: 'api_shelter_show', methods: ['GET'])]
    #[OA\Get(summary: 'Shelter details')]
    #[OA\Response(response: 200, description: 'Shelter details', content: new OA\JsonContent(ref: '#/components/schemas/ShelterView'))]
    public function show(Shelter $shelter): JsonResponse
    {
        return new JsonResponse(ShelterView::toArray($shelter));
    }

    #[Route('/{id}/status', name: 'api_shelter_confirm', methods: ['POST'])]
    #[OA\Post(summary: 'Confirm the current status of a shelter (open / closed) and, when open, how much room is left (plenty / limited / full)')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: ConfirmShelterStatusRequest::class)))]
    #[OA\Response(response: 200, description: 'Updated shelter', content: new OA\JsonContent(ref: '#/components/schemas/ShelterView'))]
    public function confirm(#[CurrentUser] Device $device, Shelter $shelter, #[MapRequestPayload] ConfirmShelterStatusRequest $request): JsonResponse
    {
        $this->em->persist(new ShelterStatusReport($shelter, $device, $request->status, $request->comment, $request->occupancy ?? ShelterOccupancy::Unknown));
        $shelter->confirmStatus($request->status, $request->occupancy);
        $device->touch();
        $this->em->flush();

        return new JsonResponse(ShelterView::toArray($shelter));
    }
}
