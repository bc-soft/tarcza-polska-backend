<?php

declare(strict_types=1);

namespace App\Verification\Entity;

use App\Incident\Entity\Incident;
use App\Verification\Repository\VerificationWaveRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One round of questions for an incident. Ring 0 = core, each next ring pushes the frontier outwards.
 */
#[ORM\Entity(repositoryClass: VerificationWaveRepository::class)]
#[ORM\Table(name: 'verification_wave')]
#[ORM\Index(columns: ['closed_at', 'expires_at'], name: 'idx_wave_open')]
class VerificationWave
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Incident::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Incident $incident;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $ring;

    /** @var list<string> cells targeted by this wave */
    #[ORM\Column(type: Types::JSON)]
    private array $targetCells;

    #[ORM\Column(type: Types::INTEGER)]
    private int $requestsSent = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $closedAt = null;

    /** @param list<string> $targetCells */
    public function __construct(Incident $incident, int $ring, array $targetCells, int $ttlSeconds)
    {
        $this->id = Uuid::v7();
        $this->incident = $incident;
        $this->ring = $ring;
        $this->targetCells = $targetCells;
        $this->startedAt = new DateTimeImmutable();
        $this->expiresAt = $this->startedAt->modify(\sprintf('+%d seconds', $ttlSeconds));
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getIncident(): Incident
    {
        return $this->incident;
    }

    public function getRing(): int
    {
        return $this->ring;
    }

    /** @return list<string> */
    public function getTargetCells(): array
    {
        return $this->targetCells;
    }

    public function getRequestsSent(): int
    {
        return $this->requestsSent;
    }

    public function setRequestsSent(int $n): void
    {
        $this->requestsSent = $n;
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getClosedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function isOpen(): bool
    {
        return null === $this->closedAt;
    }

    public function close(): void
    {
        $this->closedAt = new DateTimeImmutable();
    }
}
