<?php

declare(strict_types=1);

namespace App\Intelligence\Service;

use Anthropic\Client;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\WebSearchTool20260209;
use App\Incident\Entity\Incident;
use App\Intelligence\Model\ResearchResult;
use Psr\Log\LoggerInterface;

/**
 * Research provider backed by the Claude API: one call with server-side web search and a strict JSON schema.
 * Selected with RESEARCH_PROVIDER=anthropic.
 */
final class ClaudeResearcher implements ResearcherInterface
{
    private ?Client $client = null;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $anthropicApiKey,
        private readonly string $anthropicModel,
    ) {
    }

    public function name(): string
    {
        return 'anthropic:'.$this->anthropicModel;
    }

    public function isEnabled(): bool
    {
        return '' !== $this->anthropicApiKey;
    }

    public function research(Incident $incident, string $placeName): ResearchResult
    {
        $message = $this->client()->messages->create(
            model: $this->anthropicModel,
            maxTokens: 4096,
            system: ResearchPrompt::SYSTEM,
            messages: [['role' => 'user', 'content' => ResearchPrompt::userPrompt($incident, $placeName)]],
            tools: [WebSearchTool20260209::with(
                maxUses: 6,
                allowedDomains: ResearchPrompt::allowedDomains(),
                userLocation: ['type' => 'approximate', 'country' => 'PL', 'timezone' => 'Europe/Warsaw'],
            )],
            outputConfig: ['format' => ['type' => 'json_schema', 'schema' => ResearchPrompt::jsonSchema()]],
        );

        if ('refusal' === $message->stopReason) {
            $this->logger->warning('Claude refused research for incident {id}', ['id' => $incident->getId()->toRfc4122()]);

            return new ResearchResult('Research niedostępny (odmowa modelu).', []);
        }

        $json = null;
        foreach ($message->content as $block) {
            if ($block instanceof TextBlock) {
                $json = $block->text;
            }
        }
        if (null === $json) {
            return new ResearchResult('Research nie zwrócił wyniku.', []);
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        return ResearchPrompt::parse($data);
    }

    private function client(): Client
    {
        return $this->client ??= new Client(apiKey: $this->anthropicApiKey);
    }
}
