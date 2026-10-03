<?php

declare(strict_types=1);

namespace App\Intelligence\Service;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Picks the research provider from RESEARCH_PROVIDER: "openai", "anthropic" or "none".
 * Wired as the factory of ResearcherInterface in config/services.yaml.
 */
final readonly class ResearcherFactory
{
    public const array PROVIDERS = ['openai', 'anthropic', 'none'];

    public function __construct(
        private OpenAiResearcher $openAi,
        private ClaudeResearcher $claude,
        private LoggerInterface $logger,
        private string $researchProvider,
    ) {
    }

    public function create(): ResearcherInterface
    {
        $provider = strtolower(trim($this->researchProvider));

        $researcher = match ($provider) {
            'openai' => $this->openAi,
            'anthropic', 'claude' => $this->claude,
            'none', '' => new NullResearcher(),
            default => throw new InvalidArgumentException(\sprintf('Unknown RESEARCH_PROVIDER "%s", expected one of: %s', $this->researchProvider, implode(', ', self::PROVIDERS))),
        };

        if (!$researcher->isEnabled() && !$researcher instanceof NullResearcher) {
            $this->logger->notice('Research provider {name} selected but no API key configured; research will be skipped.', ['name' => $researcher->name()]);
        }

        return $researcher;
    }
}
