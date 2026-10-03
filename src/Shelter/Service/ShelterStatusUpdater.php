<?php

declare(strict_types=1);

namespace App\Shelter\Service;

use App\Shared\Poi\PoiKind;
use App\Shared\Poi\PoiStatusUpdaterInterface;
use App\Shelter\Enum\ShelterOccupancy;
use App\Shelter\Enum\ShelterStatus;
use App\Shelter\Repository\ShelterRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Crowd answer "is shelter X accessible?" -> shelter status. "Problem present" means it is not accessible
 * (closed, locked, full); "no problem" means open. Occupancy is left to explicit confirmations.
 */
final readonly class ShelterStatusUpdater implements PoiStatusUpdaterInterface
{
    public function __construct(private ShelterRepository $shelters)
    {
    }

    public function kind(): PoiKind
    {
        return PoiKind::Shelter;
    }

    public function applyAnswer(Uuid $poiId, bool $problemPresent, array $context = []): void
    {
        $shelter = $this->shelters->find($poiId);
        if (null === $shelter) {
            return;
        }
        if ($problemPresent) {
            $shelter->confirmStatus(ShelterStatus::Closed);

            return;
        }
        $occupancy = ShelterStatus::Open === $shelter->getStatus() ? $shelter->getOccupancy() : ShelterOccupancy::Unknown;
        $shelter->confirmStatus(ShelterStatus::Open, $occupancy);
    }
}
