<?php

declare(strict_types=1);

namespace App\Tests\Unit\Verification;

use App\Reporting\Enum\ReportType;
use App\Verification\Enum\VerificationAnswer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VerificationAnswerTest extends TestCase
{
    #[Test]
    public function doYouHavePowerNoMeansProblemPresent(): void
    {
        $flag = ReportType::PowerOutage->yesMeansProblemPresent();

        self::assertFalse($flag);
        self::assertSame('yes', VerificationAnswer::No->normalise($flag));
        self::assertSame('no', VerificationAnswer::Yes->normalise($flag));
        self::assertSame('unknown', VerificationAnswer::Unknown->normalise($flag));
    }

    #[Test]
    public function isThereAThreatYesMeansProblemPresent(): void
    {
        $flag = ReportType::OtherThreat->yesMeansProblemPresent();

        self::assertTrue($flag);
        self::assertSame('yes', VerificationAnswer::Yes->normalise($flag));
        self::assertSame('no', VerificationAnswer::No->normalise($flag));
    }

    #[Test]
    public function everyTypeHasAQuestionAndLabel(): void
    {
        foreach (ReportType::cases() as $type) {
            self::assertNotSame('', $type->label());
            self::assertStringEndsWith('?', $type->verificationQuestion());
        }
    }
}
