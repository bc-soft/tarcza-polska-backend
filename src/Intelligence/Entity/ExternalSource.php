<?php

declare(strict_types=1);

namespace App\Intelligence\Entity;

use App\Incident\Entity\Incident;
use App\Intelligence\Repository\ExternalSourceRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Evidence from outside the crowd: operator statements, official alerts, local media.
 * Found by AI research, added by an operator, or injected by the demo simulator.
 */
#[ORM\Entity(repositoryClass: ExternalSourceRepository::class)]
#[ORM\Table(name: 'external_source')]
class ExternalSource
{
    public const string KIND_OFFICIAL = 'official';
    public const string KIND_OPERATOR = 'infrastructure_operator';
    public const string KIND_MEDIA = 'media';
    public const string KIND_SOCIAL = 'social';
    public const string KIND_OTHER = 'other';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Incident::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Incident $incident;

    #[ORM\Column(length: 2048)]
    private string $url;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $publisher = null;

    #[ORM\Column(length: 32)]
    private string $kind = self::KIND_OTHER;

    /** 0..1 how much this source should move the confidence. */
    #[ORM\Column(type: Types::FLOAT)]
    private float $credibility;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $excerpt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $foundAt;

    /** ai | operator | simulation */
    #[ORM\Column(length: 16)]
    private string $foundBy;

    public function __construct(Incident $incident, string $url, string $title, string $kind, float $credibility, string $foundBy)
    {
        $this->id = Uuid::v7();
        $this->incident = $incident;
        $this->url = mb_substr($url, 0, 2048);
        $this->title = mb_substr($title, 0, 255);
        $this->kind = $kind;
        $this->credibility = max(0.0, min(1.0, $credibility));
        $this->foundBy = $foundBy;
        $this->foundAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getIncident(): Incident
    {
        return $this->incident;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function setPublisher(?string $publisher): void
    {
        $this->publisher = null === $publisher ? null : mb_substr($publisher, 0, 255);
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getCredibility(): float
    {
        return $this->credibility;
    }

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    public function setExcerpt(?string $excerpt): void
    {
        $this->excerpt = $excerpt;
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?DateTimeImmutable $publishedAt): void
    {
        $this->publishedAt = $publishedAt;
    }

    public function getFoundAt(): DateTimeImmutable
    {
        return $this->foundAt;
    }

    public function getFoundBy(): string
    {
        return $this->foundBy;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'url' => $this->url,
            'title' => $this->title,
            'publisher' => $this->publisher,
            'kind' => $this->kind,
            'credibility' => round($this->credibility, 2),
            'excerpt' => $this->excerpt,
            'publishedAt' => $this->publishedAt?->format(\DATE_ATOM),
            'foundAt' => $this->foundAt->format(\DATE_ATOM),
            'foundBy' => $this->foundBy,
        ];
    }
}
