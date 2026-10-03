<?php

declare(strict_types=1);

namespace App\Shelter\Entity;

use App\Identity\Entity\Device;
use App\Shelter\Enum\ShelterOccupancy;
use App\Shelter\Enum\ShelterStatus;
use App\Shelter\Repository\ShelterStatusReportRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Audit trail of citizen confirmations; the aggregate status lives on Shelter. */
#[ORM\Entity(repositoryClass: ShelterStatusReportRepository::class)]
#[ORM\Table(name: 'shelter_status_report')]
#[ORM\Index(columns: ['shelter_id', 'created_at'], name: 'idx_ssr_shelter_created')]
class ShelterStatusReport
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Shelter::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shelter $shelter;

    #[ORM\ManyToOne(targetEntity: Device::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Device $device;

    #[ORM\Column(length: 16, enumType: ShelterStatus::class)]
    private ShelterStatus $status;

    #[ORM\Column(length: 16, enumType: ShelterOccupancy::class, options: ['default' => 'unknown'])]
    private ShelterOccupancy $occupancy;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(Shelter $shelter, Device $device, ShelterStatus $status, ?string $comment = null, ShelterOccupancy $occupancy = ShelterOccupancy::Unknown)
    {
        $this->occupancy = $occupancy;
        $this->id = Uuid::v7();
        $this->shelter = $shelter;
        $this->device = $device;
        $this->status = $status;
        $this->comment = $comment;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getShelter(): Shelter
    {
        return $this->shelter;
    }

    public function getDevice(): Device
    {
        return $this->device;
    }

    public function getStatus(): ShelterStatus
    {
        return $this->status;
    }

    public function getOccupancy(): ShelterOccupancy
    {
        return $this->occupancy;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
