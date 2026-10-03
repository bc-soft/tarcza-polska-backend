<?php

declare(strict_types=1);

namespace App\Command\Controller\Web;

use App\Audit\Service\AuditLogger;
use App\Identity\Dto\CreateOperatorRequest;
use App\Identity\Dto\UpdateOperatorRequest;
use App\Identity\Entity\Operator;
use App\Identity\Repository\OperatorRepository;
use App\Identity\Service\OperatorManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Command Center accounts (admins). */
#[Route('/command/operators')]
#[IsGranted('ROLE_ADMIN')]
final class OperatorWebController extends AbstractCommandController
{
    private const array ROLES = ['ROLE_ANALYST', 'ROLE_OPERATOR', 'ROLE_ADMIN'];

    public function __construct(
        private readonly OperatorRepository $operators,
        private readonly OperatorManager $manager,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('', name: 'command_operator_list', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('command/operator_list.html.twig', [
            'operators' => $this->operators->findAllOrdered(),
            'roles' => self::ROLES,
        ]);
    }

    #[Route('', name: 'command_operator_create', methods: ['POST'])]
    public function create(#[CurrentUser] Operator $admin, Request $request): Response
    {
        $this->assertCsrf('operator_create', $request);

        $dto = new CreateOperatorRequest(
            email: $request->request->getString('email'),
            displayName: $request->request->getString('displayName'),
            password: $request->request->getString('password'),
            roles: self::rolesFrom($request),
            organisation: self::nullableString($request, 'organisation'),
        );
        if ($this->flashErrors($dto)) {
            return $this->redirectToRoute('command_operator_list');
        }

        try {
            $operator = $this->manager->create($dto);
        } catch (ConflictHttpException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('command_operator_list');
        }

        $this->audit->log($admin, AuditLogger::CREATE_OPERATOR, 'operator', $operator->getId()->toRfc4122(), ['email' => $operator->getEmail(), 'roles' => $dto->roles]);
        $this->addFlash('success', \sprintf('Konto %s utworzone.', $operator->getEmail()));

        return $this->redirectToRoute('command_operator_list');
    }

    #[Route('/{id}', name: 'command_operator_update', methods: ['POST'])]
    public function update(#[CurrentUser] Operator $admin, Operator $operator, Request $request): Response
    {
        $id = $operator->getId()->toRfc4122();
        $this->assertCsrf('operator'.$id, $request);

        $dto = new UpdateOperatorRequest(
            displayName: $request->request->getString('displayName'),
            roles: self::rolesFrom($request),
            organisation: self::nullableString($request, 'organisation'),
            newPassword: self::nullableString($request, 'newPassword'),
        );
        if ($this->flashErrors($dto)) {
            return $this->redirectToRoute('command_operator_list');
        }

        $this->manager->update($operator, $dto);
        $this->audit->log($admin, null !== $dto->newPassword ? AuditLogger::RESET_PASSWORD : AuditLogger::UPDATE_OPERATOR, 'operator', $id, ['roles' => $dto->roles]);
        $this->addFlash('success', \sprintf('Konto %s zaktualizowane.', $operator->getEmail()));

        return $this->redirectToRoute('command_operator_list');
    }

    /** @return list<string> */
    private static function rolesFrom(Request $request): array
    {
        /** @var list<mixed> $raw */
        $raw = $request->request->all('roles');

        return array_values(array_filter(array_map(static fn (mixed $r) => \is_string($r) ? $r : '', $raw), static fn (string $r) => \in_array($r, self::ROLES, true)));
    }
}
