<?php

declare(strict_types=1);

namespace App\Intelligence\Service;

use App\Reporting\Enum\ReportType;
use App\Reporting\Model\PhotoAnalysis;
use OpenAI;
use OpenAI\Client;
use OpenAI\Responses\Responses\Output\OutputMessage;
use OpenAI\Responses\Responses\Output\OutputMessageContentOutputText;
use OpenAI\Responses\Responses\Output\OutputMessageContentRefusal;
use Psr\Log\LoggerInterface;

/**
 * Vision analysis through the OpenAI Responses API (image input + strict JSON schema).
 * Low reasoning effort, low image detail: this is classification, not investigation.
 */
final class OpenAiPhotoAnalyzer implements PhotoAnalyzerInterface
{
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

    public function analyze(string $imageBytes, string $mime, ReportType $reportedType): PhotoAnalysis
    {
        $prompt = \sprintf(
            <<<'TXT'
                Oceniasz zdjęcie przesłane przez obywatela do zgłoszenia typu "%s" w aplikacji odporności cywilnej.
                Odpowiedz wyłącznie w podanym schemacie JSON:
                - relevant: czy zdjęcie pokazuje cokolwiek związanego z problemem infrastrukturalnym lub zagrożeniem dla ludności (awaria, zalanie, zablokowana droga, ciemna ulica, kolejka na stację, uszkodzenie, dym itp.),
                - matchesType: czy pasuje konkretnie do zgłoszonego typu,
                - description: jedno zdanie po polsku, co widać, bez domysłów o osobach,
                - unsafe: true, jeśli zdjęcie zawiera treści nieodpowiednie do pokazania operatorowi (nagość, drastyczna przemoc, dane osobowe w zbliżeniu, treści niezwiązane i obraźliwe), unsafeReason: krótki powód albo null,
                - confidence: 0..1, jak pewna jest ocena relevant/matchesType.
                TXT,
            $reportedType->label(),
        );

        $response = $this->client()->responses()->create([
            'model' => $this->openAiModel,
            'input' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'input_text', 'text' => $prompt],
                    ['type' => 'input_image', 'image_url' => \sprintf('data:%s;base64,%s', $mime, base64_encode($imageBytes)), 'detail' => 'low'],
                ],
            ]],
            'reasoning' => ['effort' => 'low'],
            'max_output_tokens' => 2000,
            'text' => ['format' => [
                'type' => 'json_schema',
                'name' => 'photo_analysis',
                'strict' => true,
                'schema' => self::schema(),
            ]],
            'store' => false,
        ]);

        $json = $response->outputText;
        if (null === $json || '' === $json) {
            foreach ($response->output as $item) {
                if (!$item instanceof OutputMessage) {
                    continue;
                }
                foreach ($item->content as $content) {
                    if ($content instanceof OutputMessageContentRefusal) {
                        $this->logger->warning('OpenAI refused photo analysis: {refusal}', ['refusal' => $content->refusal]);

                        return new PhotoAnalysis(false, false, 'Analiza odrzucona przez model.', true, 'refused_by_model', 0.0);
                    }
                    if ($content instanceof OutputMessageContentOutputText) {
                        $json = $content->text;
                    }
                }
            }
        }
        if (null === $json || '' === $json) {
            return new PhotoAnalysis(false, false, '', false, null, 0.0);
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        return PhotoAnalysis::fromArray($data);
    }

    /** @return array<string, mixed> */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['relevant', 'matchesType', 'description', 'unsafe', 'unsafeReason', 'confidence'],
            'properties' => [
                'relevant' => ['type' => 'boolean'],
                'matchesType' => ['type' => 'boolean'],
                'description' => ['type' => 'string'],
                'unsafe' => ['type' => 'boolean'],
                'unsafeReason' => ['type' => ['string', 'null']],
                'confidence' => ['type' => 'number'],
            ],
        ];
    }

    private function client(): Client
    {
        return $this->client ??= OpenAI::client($this->openAiApiKey);
    }
}
