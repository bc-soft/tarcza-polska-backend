<?php

declare(strict_types=1);

namespace App\Shelter\Entity;

use App\Shared\Geo\Point;
use App\Shelter\Enum\ShelterAvailability;
use App\Shelter\Enum\ShelterOccupancy;
use App\Shelter\Enum\ShelterStatus;
use App\Shelter\Repository\ShelterRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ShelterRepository::class)]
#[ORM\Table(name: 'shelter')]
class Shelter
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 160)]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'geo_point')]
    private Point $location;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $capacity = null;

    #[ORM\Column(length: 16, enumType: ShelterStatus::class)]
    private ShelterStatus $status = ShelterStatus::Unknown;

    /** Room left, meaningful only while the shelter is open. */
    #[ORM\Column(length: 16, enumType: ShelterOccupancy::class, options: ['default' => 'unknown'])]
    private ShelterOccupancy $occupancy = ShelterOccupancy::Unknown;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastConfirmedAt = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $confirmationCount = 0;

    /** Source of the record: 'seed', 'import:<name>', 'operator'. */
    #[ORM\Column(length: 64)]
    private string $source;

    /** Identifier in the source register (e.g. "OZO-6D94271C9708" on dane.gov.pl). */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(length: 16, enumType: ShelterAvailability::class, options: ['default' => 'unknown'])]
    private ShelterAvailability $availability = ShelterAvailability::Unknown;

    /** @var list<string> gmina, powiat, województwo (from the register) */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $region = [];

    public function __construct(string $name, Point $location, string $source = 'seed')
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->location = $location;
        $this->source = $source;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    public function getLocation(): Point
    {
        return $this->location;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(?int $capacity): void
    {
        $this->capacity = $capacity;
    }

    public function getStatus(): ShelterStatus
    {
        return $this->status;
    }

    public function getLastConfirmedAt(): ?DateTimeImmutable
    {
        return $this->lastConfirmedAt;
    }

    public function getConfirmationCount(): int
    {
        return $this->confirmationCount;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): void
    {
        $this->externalId = null === $externalId ? null : mb_substr($externalId, 0, 64);
    }

    public function getAvailability(): ShelterAvailability
    {
        return $this->availability;
    }

    public function setAvailability(ShelterAvailability $availability): void
    {
        $this->availability = $availability;
    }

    /** @return list<string> */
    public function getRegion(): array
    {
        return $this->region;
    }

    /** @param list<string> $region */
    public function setRegion(array $region): void
    {
        $this->region = array_values($region);
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function relocate(Point $location): void
    {
        $this->location = $location;
    }

    /** Operator decision: sets the status without counting it as a citizen confirmation. */
    public function getOccupancy(): ShelterOccupancy
    {
        return $this->occupancy;
    }

    public function overrideStatus(ShelterStatus $status, ?ShelterOccupancy $occupancy = null): void
    {
        $this->applyStatus($status, $occupancy);
    }

    public function confirmStatus(ShelterStatus $status, ?ShelterOccupancy $occupancy = null): void
    {
        $this->applyStatus($status, $occupancy);
        ++$this->confirmationCount;
    }

    /**
     * Legacy "full" status is folded into open + occupancy=full; a closed shelter has no occupancy.
     */
    private function applyStatus(ShelterStatus $status, ?ShelterOccupancy $occupancy): void
    {
        if (ShelterStatus::Full === $status) {
            $status = ShelterStatus::Open;
            $occupancy = ShelterOccupancy::Full;
        }
        $this->status = $status;
        $this->occupancy = match ($status) {
            ShelterStatus::Open => $occupancy ?? ShelterOccupancy::Unknown,
            default => ShelterOccupancy::Unknown,
        };
        $this->lastConfirmedAt = new DateTimeImmutable();
    }
}
