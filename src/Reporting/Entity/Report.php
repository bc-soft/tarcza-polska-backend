<?php

declare(strict_types=1);

namespace App\Reporting\Entity;

use App\Identity\Entity\Device;
use App\Incident\Entity\Incident;
use App\Reporting\Enum\ReportType;
use App\Reporting\Repository\ReportRepository;
use App\Shared\Geo\Point;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A single citizen observation (DETECT). Immutable once stored, except for incident assignment.
 */
#[ORM\Entity(repositoryClass: ReportRepository::class)]
#[ORM\Table(name: 'report')]
#[ORM\Index(columns: ['type', 'created_at'], name: 'idx_report_type_created')]
#[ORM\Index(columns: ['h3_cell'], name: 'idx_report_h3_cell')]
class Report
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Device::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Device $device;

    #[ORM\Column(length: 32, enumType: ReportType::class)]
    private ReportType $type;

    #[ORM\Column(type: 'geo_point')]
    private Point $location;

    #[ORM\Column(length: 16)]
    private string $h3Cell;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /** Reporter reputation at submission time; weight of this report in the confidence model. */
    #[ORM\Column(type: Types::FLOAT, options: ['default' => 1.0])]
    private float $weight = 1.0;

    #[ORM\ManyToOne(targetEntity: Incident::class, inversedBy: 'reports')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Incident $incident = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photoPath = null;

    public function __construct(Device $device, ReportType $type, Point $location, string $h3Cell, ?string $description = null)
    {
        $this->id = Uuid::v7();
        $this->device = $device;
        $this->type = $type;
        $this->location = $location;
        $this->h3Cell = $h3Cell;
        $this->description = null !== $description && '' !== trim($description) ? trim($description) : null;
        $this->createdAt = new DateTimeImmutable();
        $this->weight = $device->getReputation();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getDevice(): Device
    {
        return $this->device;
    }

    public function getType(): ReportType
    {
        return $this->type;
    }

    public function getLocation(): Point
    {
        return $this->location;
    }

    public function getH3Cell(): string
    {
        return $this->h3Cell;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function getIncident(): ?Incident
    {
        return $this->incident;
    }

    public function assignTo(Incident $incident): void
    {
        $this->incident = $incident;
    }

    public function getPhotoPath(): ?string
    {
        return $this->photoPath;
    }

    public function setPhotoPath(?string $photoPath): void
    {
        $this->photoPath = $photoPath;
    }
}
