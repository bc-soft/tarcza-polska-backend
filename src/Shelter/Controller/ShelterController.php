<?php

declare(strict_types=1);

namespace App\Shelter\Controller;

use App\Identity\Entity\Device;
use App\Shared\Geo\BoundingBox;
use App\Shared\Geo\Point;
use App\Shelter\Dto\ConfirmShelterStatusRequest;
use App\Shelter\Entity\Shelter;
use App\Shelter\Entity\ShelterStatusReport;
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
    ) {
    }

    #[Route('', name: 'api_shelter_list', methods: ['GET'])]
    #[OA\Get(summary: 'Shelters in a bbox, or the nearest ones to lat/lng')]
    #[OA\Parameter(name: 'bbox', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'lat', in: 'query', schema: new OA\Schema(type: 'number'))]
    #[OA\Parameter(name: 'lng', in: 'query', schema: new OA\Schema(type: 'number'))]
    #[OA\Response(response: 200, description: 'Shelters')]
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
            $bbox = $request->query->has('bbox') ? BoundingBox::fromString((string) $request->query->get('bbox')) : BoundingBox::poland();
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        return new JsonResponse(array_map(ShelterView::toArray(...), $this->shelters->findInBoundingBox($bbox)));
    }

    #[Route('/{id}', name: 'api_shelter_show', methods: ['GET'])]
    #[OA\Get(summary: 'Shelter details')]
    #[OA\Response(response: 200, description: 'Shelter')]
    public function show(Shelter $shelter): JsonResponse
    {
        return new JsonResponse(ShelterView::toArray($shelter));
    }

    #[Route('/{id}/status', name: 'api_shelter_confirm', methods: ['POST'])]
    #[OA\Post(summary: 'Confirm the current status of a shelter (open / closed / full)')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: ConfirmShelterStatusRequest::class)))]
    #[OA\Response(response: 200, description: 'Updated shelter')]
    public function confirm(#[CurrentUser] Device $device, Shelter $shelter, #[MapRequestPayload] ConfirmShelterStatusRequest $request): JsonResponse
    {
        $this->em->persist(new ShelterStatusReport($shelter, $device, $request->status, $request->comment));
        $shelter->confirmStatus($request->status);
        $device->touch();
        $this->em->flush();

        return new JsonResponse(ShelterView::toArray($shelter));
    }
}
