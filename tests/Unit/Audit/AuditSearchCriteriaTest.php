<?php

declare(strict_types=1);

namespace App\Tests\Unit\Audit;

use App\Audit\Dto\AuditSearchCriteria;
use PHPUnit\Framework\TestCase;

final class AuditSearchCriteriaTest extends TestCase
{
    public function testBlankStringsBecomeNull(): void
    {
        $c = AuditSearchCriteria::fromStrings('  ', '', null);

        self::assertNull($c->actor);
        self::assertNull($c->action);
        self::assertNull($c->subjectType);
        self::assertSame(200, $c->limit);
    }

    public function testValuesAreTrimmedAndLimitClamped(): void
    {
        $c = AuditSearchCriteria::fromStrings(' admin@tarcza.local ', 'publish_alert', 'alert', 5000);

        self::assertSame('admin@tarcza.local', $c->actor);
        self::assertSame('publish_alert', $c->action);
        self::assertSame('alert', $c->subjectType);
        self::assertSame(1000, $c->limit);
    }
}
