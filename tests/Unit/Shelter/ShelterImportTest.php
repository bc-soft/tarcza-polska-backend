<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shelter;

use App\Shelter\Command\ShelterImportCommand;
use App\Shelter\Enum\ShelterAvailability;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShelterImportTest extends TestCase
{
    #[Test]
    public function registerAvailabilityValuesAreMapped(): void
    {
        self::assertSame(ShelterAvailability::Always, ShelterAvailability::fromRegister('Całodobowa'));
        self::assertSame(ShelterAvailability::OnDemand, ShelterAvailability::fromRegister('Na żądanie'));
        self::assertSame(ShelterAvailability::Scheduled, ShelterAvailability::fromRegister('Określone godziny'));
        self::assertSame(ShelterAvailability::Unknown, ShelterAvailability::fromRegister(''));
        self::assertSame(ShelterAvailability::Unknown, ShelterAvailability::fromRegister('???'));
    }

    #[Test]
    public function displayNameUsesAddressBecauseEveryRowIsCalledTheSame(): void
    {
        self::assertSame('Miejsce ochronne · ul. Leszczyńska 4, Długie Stare', ShelterImportCommand::displayName('Miejsce ochronne', 'ul. Leszczyńska 4, Długie Stare', 'Święciechowa'));
        self::assertSame('Miejsce ochronne · Żary', ShelterImportCommand::displayName('Miejsce ochronne', '', 'Żary'));
        self::assertSame('Miejsce ochronne', ShelterImportCommand::displayName('', '', ''));
        self::assertLessThanOrEqual(160, mb_strlen(ShelterImportCommand::displayName('X', str_repeat('a', 300), '')));
    }
}
