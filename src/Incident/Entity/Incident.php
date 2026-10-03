<?php

declare(strict_types=1);

namespace App\Incident\Entity;

use App\Fuel\Enum\FuelType;
use App\Incident\Enum\CellState;
use App\Incident\Enum\ConfidenceLevel;
use App\Incident\Enum\IncidentResolution;
use App\Incident\Enum\IncidentStatus;
use App\Incident\Repository\IncidentRepository;
use App\Reporting\Entity\Report;
use App\Reporting\Enum\ReportScope;
use App\Reporting\Enum\ReportType;
use App\Shared\Geo\Point;
use App\Shared\Poi\PoiKind;
use App\Shared\Poi\PoiRef;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * An event inferred from one or more reports. Owns the dynamic area (set of H3 cells) and the confidence.
 */
#[ORM\Entity(repositoryClass: IncidentRepository::class)]
#[ORM\Table(name: 'incident')]
#[ORM\Index(columns: ['type', 'status'], name: 'idx_incident_type_status')]
#[ORM\Index(columns: ['last_activity_at'], name: 'idx_incident_activity')]
class Incident
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 32, enumType: ReportType::class)]
    private ReportType $type;

    #[ORM\Column(length: 16, enumType: IncidentStatus::class)]
    private IncidentStatus $status = IncidentStatus::Detected;

    #[ORM\Column(type: Types::FLOAT)]
    private float $confidenceScore = 0.0;

    #[ORM\Column(length: 16, enumType: ConfidenceLevel::class)]
    private ConfidenceLevel $confidenceLevel = ConfidenceLevel::Unverified;

    /** @var array<string, mixed> explainable breakdown of the last confidence computation */
    #[ORM\Column(type: Types::JSON)]
    private array $confidenceBreakdown = [];

    #[ORM\Column(type: 'geo_point')]
    private Point $centroid;

    #[ORM\Column(length: 16)]
    private string $centerCell;

    /** @var array<string, mixed>|null GeoJSON MultiPolygon = union of positive cells */
    #[ORM\Column(type: 'geo_geometry', nullable: true)]
    private ?array $area = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $lastActivityAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastConfirmedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(length: 16, nullable: true, enumType: IncidentResolution::class)]
    private ?IncidentResolution $resolution = null;

    /** area = polygon of cells; point = one object (fuel station / shelter), never an area. */
    #[ORM\Column(length: 8, enumType: ReportScope::class, options: ['default' => 'area'])]
    private ReportScope $scope = ReportScope::Area;

    #[ORM\Column(length: 32, nullable: true, enumType: PoiKind::class)]
    private ?PoiKind $poiKind = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $poiId = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $poiName = null;

    /** @var list<string> fuel types reported missing (fuel shortage incidents) */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $fuelTypes = [];

    /** Highest verification ring asked so far (0 = core only). */
    #[ORM\Column(type: Types::SMALLINT, options: ['default' => -1])]
    private int $currentRing = -1;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aiSummary = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $researchedAt = null;

    /** @var Collection<int, Report> */
    #[ORM\OneToMany(targetEntity: Report::class, mappedBy: 'incident')]
    private Collection $reports;

    /** @var Collection<string, IncidentCell> keyed by H3 index */
    #[ORM\OneToMany(targetEntity: IncidentCell::class, mappedBy: 'incident', cascade: ['persist', 'remove'], orphanRemoval: true, indexBy: 'h3Index')]
    private Collection $cells;

    public function __construct(ReportType $type, Point $centroid, string $centerCell)
    {
        $this->id = Uuid::v7();
        $this->type = $type;
        $this->scope = $type->scope();
        $this->centroid = $centroid;
        $this->centerCell = $centerCell;
        $this->startedAt = new DateTimeImmutable();
        $this->lastActivityAt = $this->startedAt;
        $this->reports = new ArrayCollection();
        $this->cells = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getType(): ReportType
    {
        return $this->type;
    }

    public function getStatus(): IncidentStatus
    {
        return $this->status;
    }

    public function setStatus(IncidentStatus $status, ?IncidentResolution $resolution = null): void
    {
        $this->status = $status;
        if (IncidentStatus::Resolved === $status) {
            $this->resolvedAt = new DateTimeImmutable();
            $this->resolution = $resolution ?? IncidentResolution::Confirmed;
        }
    }

    public function getResolution(): ?IncidentResolution
    {
        return $this->resolution;
    }

    public function getScope(): ReportScope
    {
        return $this->scope;
    }

    public function isPointScoped(): bool
    {
        return ReportScope::Point === $this->scope;
    }

    /** Binds a point-scoped incident to its object; the centroid and center cell follow the object. */
    public function bindToPoi(PoiRef $poi, string $poiCell): void
    {
        $this->scope = ReportScope::Point;
        $this->poiKind = $poi->kind;
        $this->poiId = $poi->id;
        $this->poiName = mb_substr($poi->name, 0, 160);
        $this->centroid = $poi->location;
        $this->centerCell = $poiCell;
    }

    public function getPoiKind(): ?PoiKind
    {
        return $this->poiKind;
    }

    public function getPoiId(): ?Uuid
    {
        return $this->poiId;
    }

    public function getPoiName(): ?string
    {
        return $this->poiName;
    }

    public function poiRef(): ?PoiRef
    {
        if (null === $this->poiKind || null === $this->poiId) {
            return null;
        }

        return new PoiRef($this->poiKind, $this->poiId, (string) $this->poiName, $this->centroid);
    }

    /** @return list<FuelType> */
    public function getFuelTypes(): array
    {
        return FuelType::fromValues($this->fuelTypes);
    }

    /** @param list<FuelType> $types */
    public function mergeFuelTypes(array $types): void
    {
        $merged = $this->fuelTypes;
        foreach ($types as $type) {
            if (!\in_array($type->value, $merged, true)) {
                $merged[] = $type->value;
            }
        }
        $this->fuelTypes = $merged;
    }

    public function getConfidenceScore(): float
    {
        return $this->confidenceScore;
    }

    public function getConfidenceLevel(): ConfidenceLevel
    {
        return $this->confidenceLevel;
    }

    /** @return array<string, mixed> */
    public function getConfidenceBreakdown(): array
    {
        return $this->confidenceBreakdown;
    }

    /** @param array<string, mixed> $breakdown */
    public function updateConfidence(float $score, ConfidenceLevel $level, array $breakdown): void
    {
        $this->confidenceScore = max(0.0, min(1.0, $score));
        $this->confidenceLevel = $level;
        $this->confidenceBreakdown = $breakdown;
        if ($level->atLeast(ConfidenceLevel::High)) {
            $this->lastConfirmedAt = new DateTimeImmutable();
        }
    }

    public function getCentroid(): Point
    {
        return $this->centroid;
    }

    public function getCenterCell(): string
    {
        return $this->centerCell;
    }

    public function relocate(Point $centroid, string $centerCell): void
    {
        $this->centroid = $centroid;
        $this->centerCell = $centerCell;
    }

    /** @return array<string, mixed>|null */
    public function getArea(): ?array
    {
        return $this->area;
    }

    /** @param array<string, mixed>|null $area */
    public function setArea(?array $area): void
    {
        $this->area = $area;
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getLastActivityAt(): DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function touch(): void
    {
        $this->lastActivityAt = new DateTimeImmutable();
    }

    public function getLastConfirmedAt(): ?DateTimeImmutable
    {
        return $this->lastConfirmedAt;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function getCurrentRing(): int
    {
        return $this->currentRing;
    }

    public function setCurrentRing(int $ring): void
    {
        $this->currentRing = $ring;
    }

    public function getAiSummary(): ?string
    {
        return $this->aiSummary;
    }

    public function getResearchedAt(): ?DateTimeImmutable
    {
        return $this->researchedAt;
    }

    public function recordResearch(?string $summary): void
    {
        $this->aiSummary = $summary;
        $this->researchedAt = new DateTimeImmutable();
    }

    /** Allows research to run again (operator request or tarcza:research --again). */
    public function recordResearchReset(): void
    {
        $this->researchedAt = null;
    }

    /** @return Collection<int, Report> */
    public function getReports(): Collection
    {
        return $this->reports;
    }

    public function addReport(Report $report): void
    {
        if (!$this->reports->contains($report)) {
            $this->reports->add($report);
            $report->assignTo($this);
        }
        $this->touch();
    }

    /** @return Collection<string, IncidentCell> keyed by H3 index */
    public function getCells(): Collection
    {
        return $this->cells;
    }

    public function cell(string $h3Index, int $ring): IncidentCell
    {
        $existing = $this->cells->get($h3Index);
        if ($existing instanceof IncidentCell) {
            return $existing;
        }
        $cell = new IncidentCell($this, $h3Index, $ring);
        $this->cells->set($h3Index, $cell);

        return $cell;
    }

    /** @return list<string> */
    public function cellsInState(CellState $state): array
    {
        $out = [];
        foreach ($this->cells as $cell) {
            if ($cell->getState() === $state) {
                $out[] = $cell->getH3Index();
            }
        }

        return $out;
    }

    /** @return list<string> */
    public function positiveCells(): array
    {
        return $this->cellsInState(CellState::Positive);
    }

    /** @return array{reports: int, yes: int, no: int, unknown: int, cells: int, positive: int, negative: int} */
    public function tallies(): array
    {
        $t = ['reports' => 0, 'yes' => 0, 'no' => 0, 'unknown' => 0, 'cells' => 0, 'positive' => 0, 'negative' => 0];
        foreach ($this->cells as $cell) {
            ++$t['cells'];
            $t['reports'] += $cell->getReportCount();
            $t['yes'] += $cell->getYesCount();
            $t['no'] += $cell->getNoCount();
            $t['unknown'] += $cell->getUnknownCount();
            if (CellState::Positive === $cell->getState()) {
                ++$t['positive'];
            } elseif (CellState::Negative === $cell->getState()) {
                ++$t['negative'];
            }
        }

        return $t;
    }
}
