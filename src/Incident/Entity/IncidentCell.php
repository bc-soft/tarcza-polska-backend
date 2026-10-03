<?php

declare(strict_types=1);

namespace App\Incident\Entity;

use App\Incident\Enum\CellState;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One H3 hexagon of an incident. Aggregates reports and verification answers that fell inside it.
 * yes/no are already normalised to "problem present / problem absent" by the Verification module.
 */
#[ORM\Entity]
#[ORM\Table(name: 'incident_cell')]
#[ORM\UniqueConstraint(name: 'uniq_incident_cell', columns: ['incident_id', 'h3_index'])]
#[ORM\Index(columns: ['h3_index'], name: 'idx_incident_cell_h3')]
class IncidentCell
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Incident::class, inversedBy: 'cells')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Incident $incident;

    #[ORM\Column(length: 16)]
    private string $h3Index;

    /** Grid distance from the incident center cell at creation time. */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $ring;

    #[ORM\Column(length: 16, enumType: CellState::class)]
    private CellState $state = CellState::Unknown;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $reportCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $yesCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $noCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $unknownCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $askedCount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(Incident $incident, string $h3Index, int $ring)
    {
        $this->id = Uuid::v7();
        $this->incident = $incident;
        $this->h3Index = $h3Index;
        $this->ring = $ring;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getIncident(): Incident
    {
        return $this->incident;
    }

    public function getH3Index(): string
    {
        return $this->h3Index;
    }

    public function getRing(): int
    {
        return $this->ring;
    }

    public function getState(): CellState
    {
        return $this->state;
    }

    public function getReportCount(): int
    {
        return $this->reportCount;
    }

    public function getYesCount(): int
    {
        return $this->yesCount;
    }

    public function getNoCount(): int
    {
        return $this->noCount;
    }

    public function getUnknownCount(): int
    {
        return $this->unknownCount;
    }

    public function getAskedCount(): int
    {
        return $this->askedCount;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function addReport(): void
    {
        ++$this->reportCount;
        $this->recomputeState();
    }

    public function addAsked(int $n = 1): void
    {
        $this->askedCount += $n;
        $this->updatedAt = new DateTimeImmutable();
    }

    /** @param 'yes'|'no'|'unknown' $normalisedAnswer "yes" = problem present here */
    public function addAnswer(string $normalisedAnswer): void
    {
        match ($normalisedAnswer) {
            'yes' => ++$this->yesCount,
            'no' => ++$this->noCount,
            'unknown' => ++$this->unknownCount,
        };
        $this->recomputeState();
    }

    public function hasResponses(): bool
    {
        return ($this->yesCount + $this->noCount) > 0;
    }

    /**
     * Deterministic cell rule:
     *   evidence = reports + yes - no
     *   evidence > 0           -> positive
     *   evidence <= 0 && no>0  -> negative
     *   otherwise              -> unknown (nobody answered yet)
     */
    private function recomputeState(): void
    {
        $evidence = $this->reportCount + $this->yesCount - $this->noCount;
        $this->state = match (true) {
            $evidence > 0 => CellState::Positive,
            $this->noCount > 0 => CellState::Negative,
            default => CellState::Unknown,
        };
        $this->updatedAt = new DateTimeImmutable();
    }
}
