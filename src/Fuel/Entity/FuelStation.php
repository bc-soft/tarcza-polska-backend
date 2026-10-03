<?php

declare(strict_types=1);

namespace App\Fuel\Entity;

use App\Fuel\Enum\FuelAvailability;
use App\Fuel\Enum\FuelType;
use App\Fuel\Repository\FuelStationRepository;
use App\Shared\Geo\Point;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A fuel station: the point of interest that fuel-shortage reports and questions refer to.
 * Availability is tracked per fuel type and only ever changes through citizen/operator confirmations.
 */
#[ORM\Entity(repositoryClass: FuelStationRepository::class)]
#[ORM\Table(name: 'fuel_station')]
#[ORM\UniqueConstraint(name: 'uniq_fuel_station_external', columns: ['source', 'external_id'])]
#[ORM\Index(columns: ['h3_cell'], name: 'idx_fuel_station_h3')]
class FuelStation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 160)]
    private string $name;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $brand = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'geo_point')]
    private Point $location;

    #[ORM\Column(length: 16)]
    private string $h3Cell;

    /** osm | fixture | operator */
    #[ORM\Column(length: 32)]
    private string $source;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $externalId = null;

    /** @var list<string> fuel types the station sells (from the import); empty = unknown */
    #[ORM\Column(type: Types::JSON)]
    private array $fuelTypes = [];

    /** @var array<string, array{status: string, at: string|null}> per fuel type */
    #[ORM\Column(type: Types::JSON)]
    private array $availability = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastConfirmedAt = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $confirmationCount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(string $name, Point $location, string $h3Cell, string $source, ?string $externalId = null)
    {
        $this->id = Uuid::v7();
        $this->name = mb_substr($name, 0, 160);
        $this->location = $location;
        $this->h3Cell = $h3Cell;
        $this->source = $source;
        $this->externalId = $externalId;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = mb_substr($name, 0, 160);
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): void
    {
        $this->brand = null === $brand ? null : mb_substr($brand, 0, 80);
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): void
    {
        $this->address = null === $address ? null : mb_substr($address, 0, 255);
    }

    public function getLocation(): Point
    {
        return $this->location;
    }

    public function relocate(Point $location, string $h3Cell): void
    {
        $this->location = $location;
        $this->h3Cell = $h3Cell;
    }

    public function getH3Cell(): string
    {
        return $this->h3Cell;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    /** @return list<FuelType> */
    public function getFuelTypes(): array
    {
        return FuelType::fromValues($this->fuelTypes);
    }

    /** @param list<FuelType> $types */
    public function setFuelTypes(array $types): void
    {
        $this->fuelTypes = array_values(array_unique(array_map(static fn (FuelType $t) => $t->value, $types)));
    }

    public function availabilityOf(FuelType $type): FuelAvailability
    {
        return FuelAvailability::tryFrom($this->availability[$type->value]['status'] ?? '') ?? FuelAvailability::Unknown;
    }

    public function availabilityConfirmedAt(FuelType $type): ?DateTimeImmutable
    {
        $at = $this->availability[$type->value]['at'] ?? null;

        return \is_string($at) ? new DateTimeImmutable($at) : null;
    }

    /** @param list<FuelType> $types */
    public function confirmAvailability(array $types, FuelAvailability $status, bool $countsAsConfirmation = true): void
    {
        $now = new DateTimeImmutable();
        foreach ($types as $type) {
            $this->availability[$type->value] = ['status' => $status->value, 'at' => $now->format(\DATE_ATOM)];
        }
        $this->lastConfirmedAt = $now;
        if ($countsAsConfirmation) {
            ++$this->confirmationCount;
        }
    }

    /** True when any tracked fuel type is currently reported as missing. */
    public function hasShortage(): bool
    {
        foreach ($this->availability as $row) {
            if (FuelAvailability::Unavailable->value === ($row['status'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<FuelType> */
    public function missingFuelTypes(): array
    {
        $out = [];
        foreach ($this->availability as $value => $row) {
            $type = FuelType::tryFrom((string) $value);
            if (null !== $type && FuelAvailability::Unavailable->value === ($row['status'] ?? null)) {
                $out[] = $type;
            }
        }

        return $out;
    }

    public function getLastConfirmedAt(): ?DateTimeImmutable
    {
        return $this->lastConfirmedAt;
    }

    public function getConfirmationCount(): int
    {
        return $this->confirmationCount;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
