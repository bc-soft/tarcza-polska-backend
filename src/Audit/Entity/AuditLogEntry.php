<?php

declare(strict_types=1);

namespace App\Audit\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Who looked at (or did) what in the Command Center. Append-only.
 */
#[ORM\Entity]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(columns: ['actor', 'created_at'], name: 'idx_audit_actor_created')]
#[ORM\Index(columns: ['subject_type', 'subject_id'], name: 'idx_audit_subject')]
class AuditLogEntry
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 180)]
    private string $actor;

    #[ORM\Column(length: 64)]
    private string $action;

    #[ORM\Column(length: 64)]
    private string $subjectType;

    #[ORM\Column(length: 64)]
    private string $subjectId;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $context;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /** @param array<string, mixed> $context */
    public function __construct(string $actor, string $action, string $subjectType, string $subjectId, array $context = [], ?string $ip = null)
    {
        $this->id = Uuid::v7();
        $this->actor = $actor;
        $this->action = $action;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->context = $context;
        $this->ip = $ip;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getActor(): string
    {
        return $this->actor;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getSubjectType(): string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): string
    {
        return $this->subjectId;
    }

    /** @return array<string, mixed> */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
