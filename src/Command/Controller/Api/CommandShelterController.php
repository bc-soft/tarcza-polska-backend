<?php

declare(strict_types=1);

namespace App\Command\Controller\Api;

use App\Audit\Service\AuditLogger;
use App\Identity\Entity\Operator;
use App\Shelter\Dto\CreateShelterRequest;
use App\Shelter\Dto\SetShelterStatusRequest;
use App\Shelter\Dto\UpdateShelterRequest;
use App\Shelter\Entity\Shelter;
use App\Shelter\Repository\ShelterRepository;
use App\Shelter\Service\ShelterManager;
use App\Shelter\View\ShelterView;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/command/shelters')]
#[OA\Tag(name: 'Command')]
final class CommandShelterController
{
    public function __construct(
        private readonly ShelterRepository $shelters,
        private readonly ShelterManager $manager,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('', name: 'api_command_shelter_list', methods: ['GET'])]
    #[OA\Get(summary: 'All shelters (registry view, alphabetical)')]
    #[OA\Response(response: 200, description: 'Shelters')]
    public function list(): JsonResponse
    {
        return new JsonResponse(array_map(ShelterView::toArray(...), $this->shelters->findAllOrdered()));
    }

    #[Route('', name: 'api_command_shelter_create', methods: ['POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    #[OA\Post(summary: 'Register a shelter')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: CreateShelterRequest::class)))]
    #[OA\Response(response: 201, description: 'Shelter created')]
    public function create(#[CurrentUser] Operator $operator, #[MapRequestPayload] CreateShelterRequest $request): JsonResponse
    {
        $shelter = $this->manager->create($request);
        $this->audit->log($operator, AuditLogger::CREATE_SHELTER, 'shelter', $shelter->getId()->toRfc4122(), ['name' => $request->name]);

        return new JsonResponse(ShelterView::toArray($shelter), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_command_shelter_update', methods: ['PUT'])]
    #[IsGranted('ROLE_OPERATOR')]
    #[OA\Put(summary: 'Update shelter master data (name, address, location, capacity)')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: UpdateShelterRequest::class)))]
    #[OA\Response(response: 200, description: 'Updated shelter')]
    public function update(#[CurrentUser] Operator $operator, Shelter $shelter, #[MapRequestPayload] UpdateShelterRequest $request): JsonResponse
    {
        $this->manager->update($shelter, $request);
        $this->audit->log($operator, AuditLogger::UPDATE_SHELTER, 'shelter', $shelter->getId()->toRfc4122());

        return new JsonResponse(ShelterView::toArray($shelter));
    }

    #[Route('/{id}/status', name: 'api_command_shelter_status', methods: ['POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    #[OA\Post(summary: 'Operator override of the shelter status (not counted as a citizen confirmation)')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: SetShelterStatusRequest::class)))]
    #[OA\Response(response: 200, description: 'Updated shelter')]
    public function status(#[CurrentUser] Operator $operator, Shelter $shelter, #[MapRequestPayload] SetShelterStatusRequest $request): JsonResponse
    {
        $this->manager->setStatus($shelter, $request);
        $this->audit->log($operator, AuditLogger::SET_SHELTER_STATUS, 'shelter', $shelter->getId()->toRfc4122(), ['status' => $request->status->value]);

        return new JsonResponse(ShelterView::toArray($shelter));
    }
}
