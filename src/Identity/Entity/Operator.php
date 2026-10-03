<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Repository\OperatorRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Command Center user. Roles: ROLE_ANALYST (read), ROLE_OPERATOR (send alerts), ROLE_ADMIN.
 */
#[ORM\Entity(repositoryClass: OperatorRepository::class)]
#[ORM\Table(name: 'operator')]
#[ORM\UniqueConstraint(name: 'uniq_operator_email', columns: ['email'])]
class Operator implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 120)]
    private string $displayName;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $organisation = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /** @param list<string> $roles */
    public function __construct(string $email, string $displayName, array $roles = ['ROLE_ANALYST'])
    {
        $email = mb_strtolower(trim($email));
        if ('' === $email) {
            throw new InvalidArgumentException('Operator e-mail must not be empty');
        }
        $this->id = Uuid::v7();
        $this->email = $email;
        $this->displayName = $displayName;
        $this->roles = $roles;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    /** @return non-empty-string */
    public function getEmail(): string
    {
        return '' !== $this->email ? $this->email : throw new LogicException('Operator without e-mail');
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getOrganisation(): ?string
    {
        return $this->organisation;
    }

    public function setOrganisation(?string $organisation): void
    {
        $this->organisation = $organisation;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_ANALYST']));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    public function eraseCredentials(): void
    {
    }

    /** @return non-empty-string */
    public function getUserIdentifier(): string
    {
        return $this->getEmail();
    }
}
