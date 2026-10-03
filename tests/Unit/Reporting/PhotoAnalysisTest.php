<?php

declare(strict_types=1);

namespace App\Tests\Unit\Reporting;

use App\Intelligence\Service\NullPhotoAnalyzer;
use App\Intelligence\Service\OpenAiPhotoAnalyzer;
use App\Intelligence\Service\PhotoAnalyzerFactory;
use App\Reporting\Model\PhotoAnalysis;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class PhotoAnalysisTest extends TestCase
{
    #[Test]
    public function parsesProviderJsonAndRoundTrips(): void
    {
        $analysis = PhotoAnalysis::fromArray([
            'relevant' => true, 'matchesType' => false, 'description' => 'Ciemna ulica, brak oświetlenia.',
            'unsafe' => false, 'unsafeReason' => null, 'confidence' => 0.8125,
        ]);

        self::assertTrue($analysis->relevant);
        self::assertFalse($analysis->matchesType);
        self::assertNull($analysis->unsafeReason);
        self::assertSame(0.81, $analysis->toArray()['confidence']);
    }

    #[Test]
    public function toleratesMalformedPayload(): void
    {
        $analysis = PhotoAnalysis::fromArray(['relevant' => 'yes', 'description' => 12, 'unsafeReason' => '', 'confidence' => 'n/a']);

        self::assertTrue($analysis->relevant);
        self::assertSame('', $analysis->description);
        self::assertNull($analysis->unsafeReason);
        self::assertSame(0.0, $analysis->confidence);
    }

    #[Test]
    public function schemaIsStrict(): void
    {
        $schema = OpenAiPhotoAnalyzer::schema();

        self::assertFalse($schema['additionalProperties']);
        self::assertSame(array_keys($schema['properties']), $schema['required']);
    }

    #[Test]
    public function factoryFollowsResearchProvider(): void
    {
        $openAi = new OpenAiPhotoAnalyzer(new NullLogger(), 'sk-test', 'gpt-5');

        self::assertInstanceOf(OpenAiPhotoAnalyzer::class, new PhotoAnalyzerFactory($openAi, 'openai')->create());
        self::assertInstanceOf(NullPhotoAnalyzer::class, new PhotoAnalyzerFactory($openAi, 'anthropic')->create());
        self::assertInstanceOf(NullPhotoAnalyzer::class, new PhotoAnalyzerFactory($openAi, 'none')->create());
        self::assertFalse(new OpenAiPhotoAnalyzer(new NullLogger(), '', 'gpt-5')->isEnabled());
    }
}
