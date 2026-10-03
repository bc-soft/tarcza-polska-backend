<?php

declare(strict_types=1);

namespace App\Shelter\Service;

use App\Shared\Geo\Point;
use App\Shelter\Dto\CreateShelterRequest;
use App\Shelter\Dto\SetShelterStatusRequest;
use App\Shelter\Dto\UpdateShelterRequest;
use App\Shelter\Entity\Shelter;
use Doctrine\ORM\EntityManagerInterface;

/** Operator-side writes to shelters (the citizen confirmation flow lives in ShelterController). */
final readonly class ShelterManager
{
    public const string SOURCE_OPERATOR = 'operator';

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function create(CreateShelterRequest $request): Shelter
    {
        $shelter = new Shelter(trim($request->name), new Point($request->lat, $request->lng), self::SOURCE_OPERATOR);
        $shelter->setAddress(self::nullIfBlank($request->address));
        $shelter->setCapacity($request->capacity);
        $this->em->persist($shelter);
        $this->em->flush();

        return $shelter;
    }

    public function update(Shelter $shelter, UpdateShelterRequest $request): Shelter
    {
        $shelter->rename(trim($request->name));
        $shelter->relocate(new Point($request->lat, $request->lng));
        $shelter->setAddress(self::nullIfBlank($request->address));
        $shelter->setCapacity($request->capacity);
        $this->em->flush();

        return $shelter;
    }

    public function setStatus(Shelter $shelter, SetShelterStatusRequest $request): Shelter
    {
        $shelter->overrideStatus($request->status);
        $this->em->flush();

        return $shelter;
    }

    private static function nullIfBlank(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
