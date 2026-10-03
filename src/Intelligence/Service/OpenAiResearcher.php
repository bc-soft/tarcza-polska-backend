<?php

declare(strict_types=1);

namespace App\Intelligence\Service;

use App\Incident\Entity\Incident;
use App\Intelligence\Model\ResearchResult;
use OpenAI;
use OpenAI\Client;
use OpenAI\Responses\Responses\Output\OutputMessage;
use OpenAI\Responses\Responses\Output\OutputMessageContentOutputText;
use OpenAI\Responses\Responses\Output\OutputMessageContentRefusal;
use Psr\Log\LoggerInterface;

/**
 * Research provider backed by the OpenAI Responses API: hosted web search + strict JSON schema output.
 * Selected with RESEARCH_PROVIDER=openai. Same prompt, schema and trusted domains as the Claude provider.
 */
final class OpenAiResearcher implements ResearcherInterface
{
    /** OpenAI's web search accepts at most 20 domains in the allow-list. */
    private const int MAX_ALLOWED_DOMAINS = 20;

    private ?Client $client = null;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $openAiApiKey,
        private readonly string $openAiModel,
    ) {
    }

    public function name(): string
    {
        return 'openai:'.$this->openAiModel;
    }

    public function isEnabled(): bool
    {
        return '' !== $this->openAiApiKey;
    }

    public function research(Incident $incident, string $placeName): ResearchResult
    {
        $response = $this->client()->responses()->create([
            'model' => $this->openAiModel,
            'instructions' => ResearchPrompt::SYSTEM,
            'input' => ResearchPrompt::userPrompt($incident, $placeName),
            'tools' => [[
                'type' => 'web_search',
                'filters' => ['allowed_domains' => ResearchPrompt::allowedDomains(self::MAX_ALLOWED_DOMAINS)],
                'user_location' => ['type' => 'approximate', 'country' => 'PL', 'timezone' => 'Europe/Warsaw'],
                'search_context_size' => 'medium',
            ]],
            'tool_choice' => 'auto',
            'max_tool_calls' => 6,
            'max_output_tokens' => 4096,
            'text' => ['format' => [
                'type' => 'json_schema',
                'name' => 'incident_research',
                'strict' => true,
                'schema' => ResearchPrompt::jsonSchema(),
            ]],
            'store' => false,
        ]);

        if ('completed' !== $response->status) {
            $this->logger->warning('OpenAI research for incident {id} ended with status {status}', [
                'id' => $incident->getId()->toRfc4122(),
                'status' => $response->status,
                'reason' => $response->incompleteDetails?->reason,
            ]);
        }

        $json = $response->outputText;
        if (null === $json || '' === $json) {
            foreach ($response->output as $item) {
                if (!$item instanceof OutputMessage) {
                    continue;
                }
                foreach ($item->content as $content) {
                    if ($content instanceof OutputMessageContentRefusal) {
                        $this->logger->warning('OpenAI refused research for incident {id}: {refusal}', ['id' => $incident->getId()->toRfc4122(), 'refusal' => $content->refusal]);

                        return new ResearchResult('Research niedostępny (odmowa modelu).', []);
                    }
                    if ($content instanceof OutputMessageContentOutputText) {
                        $json = $content->text;
                    }
                }
            }
        }
        if (null === $json || '' === $json) {
            return new ResearchResult('Research nie zwrócił wyniku.', []);
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        return ResearchPrompt::parse($data);
    }

    private function client(): Client
    {
        return $this->client ??= OpenAI::client($this->openAiApiKey);
    }
}
