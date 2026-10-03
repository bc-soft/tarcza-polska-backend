<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Dto\CreateOperatorRequest;
use App\Identity\Dto\UpdateOperatorRequest;
use App\Identity\Entity\Operator;
use App\Identity\Repository\OperatorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Account management for the Command Center (admin only). */
final readonly class OperatorManager
{
    public function __construct(
        private OperatorRepository $operators,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    public function create(CreateOperatorRequest $request): Operator
    {
        $email = mb_strtolower(trim($request->email));
        if (null !== $this->operators->findOneBy(['email' => $email])) {
            throw new ConflictHttpException(\sprintf('Konto %s już istnieje.', $email));
        }

        $operator = new Operator($email, trim($request->displayName), self::normaliseRoles($request->roles));
        $operator->setPassword($this->hasher->hashPassword($operator, $request->password));
        $operator->setOrganisation(self::nullIfBlank($request->organisation));
        $this->em->persist($operator);
        $this->em->flush();

        return $operator;
    }

    public function update(Operator $operator, UpdateOperatorRequest $request): Operator
    {
        $operator->rename(trim($request->displayName));
        $operator->setRoles(self::normaliseRoles($request->roles));
        $operator->setOrganisation(self::nullIfBlank($request->organisation));
        if (null !== $request->newPassword && '' !== $request->newPassword) {
            $operator->setPassword($this->hasher->hashPassword($operator, $request->newPassword));
        }
        $this->em->flush();

        return $operator;
    }

    /**
     * @param list<string> $roles
     *
     * @return list<string>
     */
    private static function normaliseRoles(array $roles): array
    {
        $roles = array_values(array_unique(array_filter($roles, static fn (string $r) => '' !== $r)));

        return [] === $roles ? ['ROLE_ANALYST'] : $roles;
    }

    private static function nullIfBlank(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
