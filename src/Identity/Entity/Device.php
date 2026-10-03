<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Enum\LocationSource;
use App\Identity\Repository\DeviceRepository;
use App\Shared\Geo\Point;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Anonymous citizen identity. One row per app installation.
 * No personal data is stored: only the last known position (one value, no history) and a push token.
 */
#[ORM\Entity(repositoryClass: DeviceRepository::class)]
#[ORM\Table(name: 'device')]
#[ORM\Index(columns: ['h3_cell'], name: 'idx_device_h3_cell')]
#[ORM\Index(columns: ['last_seen_at'], name: 'idx_device_last_seen')]
class Device implements UserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $lastSeenAt;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $platform = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $appVersion = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $pushToken = null;

    #[ORM\Column(type: 'geo_point', nullable: true)]
    private ?Point $lastLocation = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $h3Cell = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $locationUpdatedAt = null;

    #[ORM\Column(length: 16, nullable: true, enumType: LocationSource::class)]
    private ?LocationSource $locationSource = null;

    /** Last time a verification question was pushed to this device (cooldown). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastAskedAt = null;

    /** 0..2, multiplies the weight of this device's reports and answers. 1.0 = neutral. */
    #[ORM\Column(type: Types::FLOAT, options: ['default' => 1.0])]
    private float $reputation = 1.0;

    /** User preference: may receive the location_refresh reminder push. */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $locationRefreshEnabled = true;

    /** Last time the location_refresh reminder was pushed (at most once a day). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastLocationRefreshAt = null;

    /** Virtual device created by the demo simulator. Never receives pushes. */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $simulated = false;

    public function __construct(?Uuid $id = null)
    {
        $this->id = $id ?? Uuid::v7();
        $this->createdAt = new DateTimeImmutable();
        $this->lastSeenAt = $this->createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastSeenAt(): DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function touch(): void
    {
        $this->lastSeenAt = new DateTimeImmutable();
    }

    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    public function setPlatform(?string $platform): void
    {
        $this->platform = $platform;
    }

    public function getAppVersion(): ?string
    {
        return $this->appVersion;
    }

    public function setAppVersion(?string $appVersion): void
    {
        $this->appVersion = $appVersion;
    }

    public function getPushToken(): ?string
    {
        return $this->pushToken;
    }

    public function setPushToken(?string $pushToken): void
    {
        $this->pushToken = $pushToken;
    }

    public function getLastLocation(): ?Point
    {
        return $this->lastLocation;
    }

    public function getH3Cell(): ?string
    {
        return $this->h3Cell;
    }

    public function updateLocation(Point $point, string $h3Cell, LocationSource $source = LocationSource::Gps): void
    {
        $this->lastLocation = $point;
        $this->h3Cell = $h3Cell;
        $this->locationSource = $source;
        $this->locationUpdatedAt = new DateTimeImmutable();
        $this->touch();
    }

    public function getLocationUpdatedAt(): ?DateTimeImmutable
    {
        return $this->locationUpdatedAt;
    }

    public function getLocationSource(): ?LocationSource
    {
        return $this->locationSource;
    }

    public function getLastAskedAt(): ?DateTimeImmutable
    {
        return $this->lastAskedAt;
    }

    public function markAsked(): void
    {
        $this->lastAskedAt = new DateTimeImmutable();
    }

    public function isLocationRefreshEnabled(): bool
    {
        return $this->locationRefreshEnabled;
    }

    public function setLocationRefreshEnabled(bool $enabled): void
    {
        $this->locationRefreshEnabled = $enabled;
    }

    public function getLastLocationRefreshAt(): ?DateTimeImmutable
    {
        return $this->lastLocationRefreshAt;
    }

    public function markLocationRefreshSent(DateTimeImmutable $at): void
    {
        $this->lastLocationRefreshAt = $at;
    }

    public function getReputation(): float
    {
        return $this->reputation;
    }

    public function adjustReputation(float $delta): void
    {
        $this->reputation = max(0.0, min(2.0, $this->reputation + $delta));
    }

    public function isSimulated(): bool
    {
        return $this->simulated;
    }

    public function markSimulated(): void
    {
        $this->simulated = true;
    }

    public function canReceivePush(): bool
    {
        return !$this->simulated && null !== $this->pushToken;
    }

    // --- UserInterface ---------------------------------------------------

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_CITIZEN'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->id->toRfc4122();
    }
}
