<?php

declare(strict_types=1);

namespace App\Reporting\Entity;

use App\Reporting\Model\PhotoAnalysis;
use App\Reporting\Repository\ReportPhotoRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A citizen photo attached to a report. Stored re-encoded (no EXIF, no GPS, bounded size) under `path`
 * in the photos storage; analysed asynchronously by the configured vision provider.
 */
#[ORM\Entity(repositoryClass: ReportPhotoRepository::class)]
#[ORM\Table(name: 'report_photo')]
#[ORM\Index(columns: ['report_id', 'created_at'], name: 'idx_report_photo_report_created')]
class ReportPhoto
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Report::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Report $report;

    #[ORM\Column(length: 255)]
    private string $path;

    #[ORM\Column(length: 64)]
    private string $mime;

    #[ORM\Column(type: Types::INTEGER)]
    private int $width;

    #[ORM\Column(type: Types::INTEGER)]
    private int $height;

    #[ORM\Column(type: Types::INTEGER)]
    private int $bytes;

    #[ORM\Column(length: 64)]
    private string $sha256;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $analyzedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $analyzer = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $relevant = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $matchesType = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Moderation verdict; unsafe photos are hidden from the operator gallery by default. */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $unsafe = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $unsafeReason = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $analysisConfidence = null;

    public function __construct(Report $report, string $path, string $mime, int $width, int $height, int $bytes, string $sha256)
    {
        $this->id = Uuid::v7();
        $this->report = $report;
        $this->path = $path;
        $this->mime = $mime;
        $this->width = $width;
        $this->height = $height;
        $this->bytes = $bytes;
        $this->sha256 = $sha256;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getReport(): Report
    {
        return $this->report;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMime(): string
    {
        return $this->mime;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getBytes(): int
    {
        return $this->bytes;
    }

    public function getSha256(): string
    {
        return $this->sha256;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAnalyzedAt(): ?DateTimeImmutable
    {
        return $this->analyzedAt;
    }

    public function getAnalyzer(): ?string
    {
        return $this->analyzer;
    }

    public function isAnalyzed(): bool
    {
        return null !== $this->analyzedAt;
    }

    public function isRelevant(): ?bool
    {
        return $this->relevant;
    }

    public function matchesType(): ?bool
    {
        return $this->matchesType;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isUnsafe(): bool
    {
        return $this->unsafe;
    }

    public function getUnsafeReason(): ?string
    {
        return $this->unsafeReason;
    }

    public function getAnalysisConfidence(): ?float
    {
        return $this->analysisConfidence;
    }

    public function recordAnalysis(PhotoAnalysis $analysis, string $analyzer): void
    {
        $this->analyzedAt = new DateTimeImmutable();
        $this->analyzer = mb_substr($analyzer, 0, 64);
        $this->relevant = $analysis->relevant;
        $this->matchesType = $analysis->matchesType;
        $this->description = $analysis->description;
        $this->unsafe = $analysis->unsafe;
        $this->unsafeReason = null === $analysis->unsafeReason ? null : mb_substr($analysis->unsafeReason, 0, 255);
        $this->analysisConfidence = max(0.0, min(1.0, $analysis->confidence));
    }

    /** Marks the photo as processed when no analyzer is configured, so it does not look stuck. */
    public function markSkipped(string $reason): void
    {
        $this->analyzedAt = new DateTimeImmutable();
        $this->analyzer = mb_substr($reason, 0, 64);
    }
}
