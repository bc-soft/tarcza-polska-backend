<?php

declare(strict_types=1);

namespace App\Shelter\Entity;

use App\Shared\Geo\Point;
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

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastConfirmedAt = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $confirmationCount = 0;

    /** Source of the record: 'seed', 'import:<name>', 'operator'. */
    #[ORM\Column(length: 64)]
    private string $source;

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

    public function confirmStatus(ShelterStatus $status): void
    {
        $this->status = $status;
        $this->lastConfirmedAt = new DateTimeImmutable();
        ++$this->confirmationCount;
    }
}
