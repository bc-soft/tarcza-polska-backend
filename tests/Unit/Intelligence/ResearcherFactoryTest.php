<?php

declare(strict_types=1);

namespace App\Tests\Unit\Intelligence;

use App\Intelligence\Service\ClaudeResearcher;
use App\Intelligence\Service\NullResearcher;
use App\Intelligence\Service\OpenAiResearcher;
use App\Intelligence\Service\ResearcherFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ResearcherFactoryTest extends TestCase
{
    #[Test]
    public function selectsProviderFromEnvironmentValue(): void
    {
        self::assertInstanceOf(OpenAiResearcher::class, $this->factory('openai')->create());
        self::assertInstanceOf(ClaudeResearcher::class, $this->factory('anthropic')->create());
        self::assertInstanceOf(ClaudeResearcher::class, $this->factory(' Claude ')->create());
        self::assertInstanceOf(NullResearcher::class, $this->factory('none')->create());
        self::assertInstanceOf(NullResearcher::class, $this->factory('')->create());
    }

    #[Test]
    public function rejectsUnknownProvider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->factory('gemini')->create();
    }

    #[Test]
    public function providersReportCredentialsAndName(): void
    {
        $openAi = new OpenAiResearcher(new NullLogger(), '', 'gpt-5');
        self::assertFalse($openAi->isEnabled());
        self::assertSame('openai:gpt-5', $openAi->name());

        $claude = new ClaudeResearcher(new NullLogger(), 'sk-ant-test', 'claude-opus-5');
        self::assertTrue($claude->isEnabled());
        self::assertSame('anthropic:claude-opus-5', $claude->name());
    }

    private function factory(string $provider): ResearcherFactory
    {
        return new ResearcherFactory(
            new OpenAiResearcher(new NullLogger(), 'sk-test', 'gpt-5'),
            new ClaudeResearcher(new NullLogger(), 'sk-ant-test', 'claude-opus-5'),
            new NullLogger(),
            $provider,
        );
    }
}
