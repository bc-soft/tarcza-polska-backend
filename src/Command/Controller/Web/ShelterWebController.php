<?php

declare(strict_types=1);

namespace App\Command\Controller\Web;

use App\Audit\Service\AuditLogger;
use App\Identity\Entity\Operator;
use App\Shelter\Dto\CreateShelterRequest;
use App\Shelter\Dto\SetShelterStatusRequest;
use App\Shelter\Dto\UpdateShelterRequest;
use App\Shelter\Entity\Shelter;
use App\Shelter\Enum\ShelterStatus;
use App\Shelter\Repository\ShelterRepository;
use App\Shelter\Repository\ShelterStatusReportRepository;
use App\Shelter\Service\ShelterManager;
use App\Shelter\View\ShelterView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Shelter registry: list + map, create/edit, operator status override. */
#[Route('/command/shelters')]
final class ShelterWebController extends AbstractCommandController
{
    public function __construct(
        private readonly ShelterRepository $shelters,
        private readonly ShelterManager $manager,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('', name: 'command_shelter_list', methods: ['GET'])]
    public function list(ShelterStatusReportRepository $reports): Response
    {
        $all = $this->shelters->findAllOrdered();

        return $this->render('command/shelter_list.html.twig', [
            'shelters' => $all,
            'features' => array_map(ShelterView::toFeature(...), $all),
            'counts' => $this->shelters->countByStatus(),
            'statuses' => ShelterStatus::cases(),
            'recentReports' => $reports->findRecent(20),
        ]);
    }

    #[Route('', name: 'command_shelter_create', methods: ['POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function create(#[CurrentUser] Operator $operator, Request $request): Response
    {
        $this->assertCsrf('shelter_create', $request);

        $dto = new CreateShelterRequest(
            name: $request->request->getString('name'),
            lat: (float) $request->request->get('lat', 0),
            lng: (float) $request->request->get('lng', 0),
            address: self::nullableString($request, 'address'),
            capacity: '' === $request->request->getString('capacity') ? null : $request->request->getInt('capacity'),
        );
        if ($this->flashErrors($dto)) {
            return $this->redirectToRoute('command_shelter_list');
        }

        $shelter = $this->manager->create($dto);
        $this->audit->log($operator, AuditLogger::CREATE_SHELTER, 'shelter', $shelter->getId()->toRfc4122(), ['name' => $dto->name]);
        $this->addFlash('success', \sprintf('Schron „%s” dodany.', $shelter->getName()));

        return $this->redirectToRoute('command_shelter_list');
    }

    #[Route('/{id}', name: 'command_shelter_update', methods: ['POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function update(#[CurrentUser] Operator $operator, Shelter $shelter, Request $request): Response
    {
        $id = $shelter->getId()->toRfc4122();
        $this->assertCsrf('shelter'.$id, $request);

        $dto = new UpdateShelterRequest(
            name: $request->request->getString('name'),
            lat: (float) $request->request->get('lat', 0),
            lng: (float) $request->request->get('lng', 0),
            address: self::nullableString($request, 'address'),
            capacity: '' === $request->request->getString('capacity') ? null : $request->request->getInt('capacity'),
        );
        if ($this->flashErrors($dto)) {
            return $this->redirectToRoute('command_shelter_list');
        }

        $this->manager->update($shelter, $dto);
        $this->audit->log($operator, AuditLogger::UPDATE_SHELTER, 'shelter', $id);
        $this->addFlash('success', \sprintf('Schron „%s” zaktualizowany.', $shelter->getName()));

        return $this->redirectToRoute('command_shelter_list');
    }

    #[Route('/{id}/status', name: 'command_shelter_status', methods: ['POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function status(#[CurrentUser] Operator $operator, Shelter $shelter, Request $request): Response
    {
        $id = $shelter->getId()->toRfc4122();
        $this->assertCsrf('shelter_status'.$id, $request);

        $status = ShelterStatus::tryFrom($request->request->getString('status'));
        if (null === $status) {
            $this->addFlash('error', 'Nieznany status schronu.');

            return $this->redirectToRoute('command_shelter_list');
        }

        $this->manager->setStatus($shelter, new SetShelterStatusRequest($status));
        $this->audit->log($operator, AuditLogger::SET_SHELTER_STATUS, 'shelter', $id, ['status' => $status->value]);
        $this->addFlash('success', \sprintf('Status schronu „%s”: %s.', $shelter->getName(), $status->label()));

        return $this->redirectToRoute('command_shelter_list');
    }
}
