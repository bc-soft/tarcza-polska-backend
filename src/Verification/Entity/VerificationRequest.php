<?php

declare(strict_types=1);

namespace App\Verification\Entity;

use App\Identity\Entity\Device;
use App\Incident\Entity\Incident;
use App\Shared\Poi\PoiKind;
use App\Shared\Poi\PoiRef;
use App\Verification\Enum\VerificationAnswer;
use App\Verification\Repository\VerificationRequestRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * A question pushed to one device. The answer is stored raw and normalised ("problem present here?").
 */
#[ORM\Entity(repositoryClass: VerificationRequestRepository::class)]
#[ORM\Table(name: 'verification_request')]
#[ORM\Index(columns: ['device_id', 'answered_at', 'expires_at'], name: 'idx_vr_pending')]
class VerificationRequest
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: VerificationWave::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private VerificationWave $wave;

    #[ORM\ManyToOne(targetEntity: Incident::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Incident $incident;

    #[ORM\ManyToOne(targetEntity: Device::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Device $device;

    #[ORM\Column(length: 16)]
    private string $h3Cell;

    #[ORM\Column(length: 255)]
    private string $question;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $sentAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $answeredAt = null;

    #[ORM\Column(length: 8, nullable: true, enumType: VerificationAnswer::class)]
    private ?VerificationAnswer $answer = null;

    /** Normalised to "problem present here?": yes | no | unknown. */
    #[ORM\Column(length: 8, nullable: true)]
    private ?string $normalisedAnswer = null;

    /** Point verification: the object this question is about (may be a neighbour of the incident's object). */
    #[ORM\Column(length: 32, nullable: true, enumType: PoiKind::class)]
    private ?PoiKind $poiKind = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $poiId = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $poiName = null;

    public function __construct(VerificationWave $wave, Device $device, string $h3Cell, string $question, ?PoiRef $poi = null)
    {
        if (null !== $poi) {
            $this->poiKind = $poi->kind;
            $this->poiId = $poi->id;
            $this->poiName = mb_substr($poi->name, 0, 160);
        }
        $this->id = Uuid::v7();
        $this->wave = $wave;
        $this->incident = $wave->getIncident();
        $this->device = $device;
        $this->h3Cell = $h3Cell;
        $this->question = $question;
        $this->sentAt = new DateTimeImmutable();
        $this->expiresAt = $wave->getExpiresAt();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getWave(): VerificationWave
    {
        return $this->wave;
    }

    public function getIncident(): Incident
    {
        return $this->incident;
    }

    public function getDevice(): Device
    {
        return $this->device;
    }

    public function getH3Cell(): string
    {
        return $this->h3Cell;
    }

    public function getQuestion(): string
    {
        return $this->question;
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

    /** True when the question is about the incident's own object (not a neighbouring one). */
    public function isAboutIncidentPoi(): bool
    {
        $incidentPoi = $this->incident->getPoiId();

        return null !== $this->poiId && null !== $incidentPoi && $this->poiId->equals($incidentPoi);
    }

    public function getSentAt(): DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getAnsweredAt(): ?DateTimeImmutable
    {
        return $this->answeredAt;
    }

    public function getAnswer(): ?VerificationAnswer
    {
        return $this->answer;
    }

    public function getNormalisedAnswer(): ?string
    {
        return $this->normalisedAnswer;
    }

    public function isPending(): bool
    {
        return null === $this->answeredAt && $this->expiresAt > new DateTimeImmutable();
    }

    public function isAnswered(): bool
    {
        return null !== $this->answeredAt;
    }

    /** @return 'yes'|'no'|'unknown' */
    public function answerWith(VerificationAnswer $answer): string
    {
        if ($this->isAnswered()) {
            throw new LogicException('Verification request already answered');
        }
        $this->answer = $answer;
        $this->answeredAt = new DateTimeImmutable();
        $this->normalisedAnswer = $answer->normalise($this->incident->getType()->yesMeansProblemPresent());

        return $this->normalisedAnswer;
    }
}
