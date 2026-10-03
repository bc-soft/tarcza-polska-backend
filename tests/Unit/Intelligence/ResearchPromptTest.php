<?php

declare(strict_types=1);

namespace App\Tests\Unit\Intelligence;

use App\Intelligence\Service\ResearchPrompt;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResearchPromptTest extends TestCase
{
    #[Test]
    public function schemaIsStrictForBothVendors(): void
    {
        $schema = ResearchPrompt::jsonSchema();

        self::assertFalse($schema['additionalProperties']);
        self::assertSame(array_keys($schema['properties']), array_values(array_intersect(array_keys($schema['properties']), $schema['required'])));
        $item = $schema['properties']['findings']['items'];
        self::assertFalse($item['additionalProperties']);
        self::assertSame(array_keys($item['properties']), $item['required']);
    }

    #[Test]
    public function allowedDomainsCanBeCappedForOpenAi(): void
    {
        self::assertGreaterThan(20, \count(ResearchPrompt::allowedDomains()));
        self::assertCount(20, ResearchPrompt::allowedDomains(20));
        self::assertContains('rcb.gov.pl', ResearchPrompt::allowedDomains(20));
    }

    #[Test]
    public function parsesFindingsAndKeepsOnlyConfirmingOnes(): void
    {
        $result = ResearchPrompt::parse([
            'summary' => 'Enea potwierdza awarię.',
            'suggestedVerificationQuestion' => null,
            'findings' => [
                ['url' => 'https://www.enea.pl/a', 'title' => 'Awaria', 'publisher' => 'Enea', 'kind' => 'infrastructure_operator', 'credibility' => 0.9, 'excerpt' => 'x', 'publishedAt' => '2026-10-03T12:00:00+02:00', 'confirmsIncident' => true],
                ['url' => 'https://onet.pl/b', 'title' => 'Inna awaria', 'publisher' => 'Onet', 'kind' => 'media', 'credibility' => 0.7, 'excerpt' => 'y', 'publishedAt' => 'not-a-date', 'confirmsIncident' => false],
            ],
        ]);

        self::assertSame('Enea potwierdza awarię.', $result->summary);
        self::assertNull($result->suggestedVerificationQuestion);
        self::assertCount(2, $result->findings);
        self::assertCount(1, $result->confirming());
        self::assertSame('https://www.enea.pl/a', $result->confirming()[0]->url);
        self::assertNotNull($result->findings[0]->publishedAt);
        self::assertNull($result->findings[1]->publishedAt);
    }

    #[Test]
    public function toleratesMalformedPayload(): void
    {
        $result = ResearchPrompt::parse(['summary' => 12, 'findings' => 'nope']);

        self::assertSame('', $result->summary);
        self::assertSame([], $result->findings);
    }
}
