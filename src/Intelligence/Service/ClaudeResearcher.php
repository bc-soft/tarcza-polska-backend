<?php

declare(strict_types=1);

namespace App\Intelligence\Service;

use Anthropic\Client;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\WebSearchTool20260209;
use App\Incident\Entity\Incident;
use App\Intelligence\Model\ResearchFinding;
use App\Intelligence\Model\ResearchResult;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Research & correlation engine: one Claude call with server-side web search and a strict JSON schema.
 * The model only supplies evidence; the deterministic ConfidenceCalculator decides what it is worth.
 */
final class ClaudeResearcher implements ResearcherInterface
{
    /** Domains worth trusting for Polish infrastructure incidents. Extend per region. */
    private const array ALLOWED_DOMAINS = [
        'gov.pl', 'rcb.gov.pl', 'pap.pl', 'enea.pl', 'tauron.pl', 'tauron-dystrybucja.pl', 'pgedystrybucja.pl',
        'energa-operator.pl', 'stoen.pl', 'aquanet.pl', 'mpwik.com.pl', 'gddkia.gov.pl', 'ztm.poznan.pl',
        'poznan.pl', 'tvn24.pl', 'polsatnews.pl', 'rmf24.pl', 'radiozet.pl', 'onet.pl', 'wp.pl', 'interia.pl',
        'gazeta.pl', 'wyborcza.pl', 'epoznan.pl', 'gloswielkopolski.pl', 'x.com', 'twitter.com',
    ];

    private ?Client $client = null;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $anthropicApiKey,
        private readonly string $anthropicModel,
    ) {
    }

    public function isEnabled(): bool
    {
        return '' !== $this->anthropicApiKey;
    }

    public function research(Incident $incident, string $placeName): ResearchResult
    {
        $t = $incident->tallies();
        $prompt = \sprintf(
            <<<'TXT'
                Jesteś analitykiem w centrum zarządzania kryzysowego. Crowdsourcing wskazuje na możliwe zdarzenie:

                - Typ: %s
                - Miejsce: %s (środek ok. %.4f, %.4f)
                - Pierwsze zgłoszenie: %s (czas lokalny: Europe/Warsaw)
                - Zgłoszeń: %d, potwierdzeń od okolicznych użytkowników: %d, zaprzeczeń: %d

                Sprawdź w internecie, czy istnieją publiczne potwierdzenia tego zdarzenia z ostatnich 24 godzin:
                komunikaty operatorów infrastruktury (energetyka, wodociągi, drogi), komunikaty urzędów i służb,
                lokalne media. Szukaj po polsku. Nie zgaduj: jeśli nic nie znajdziesz, zwróć pustą listę findings.
                Dla każdego źródła oceń credibility 0..1 (oficjalny komunikat operatora/urzędu ~0.9, duże media ~0.7,
                lokalne media ~0.6, media społecznościowe ~0.3) i czy źródło faktycznie potwierdza TO zdarzenie
                (ten typ, to miejsce, ten czas), a nie inne.
                Podsumowanie (summary) napisz po polsku, maks. 3 zdania, dla operatora dyżurnego.
                TXT,
            $incident->getType()->label(),
            $placeName,
            $incident->getCentroid()->lat,
            $incident->getCentroid()->lng,
            $incident->getStartedAt()->setTimezone(new DateTimeZone('Europe/Warsaw'))->format('Y-m-d H:i'),
            $t['reports'],
            $t['yes'],
            $t['no'],
        );

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['summary', 'findings', 'suggestedVerificationQuestion'],
            'properties' => [
                'summary' => ['type' => 'string'],
                'suggestedVerificationQuestion' => ['type' => ['string', 'null']],
                'findings' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['url', 'title', 'publisher', 'kind', 'credibility', 'excerpt', 'publishedAt', 'confirmsIncident'],
                        'properties' => [
                            'url' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                            'publisher' => ['type' => 'string'],
                            'kind' => ['type' => 'string', 'enum' => ['official', 'infrastructure_operator', 'media', 'social', 'other']],
                            'credibility' => ['type' => 'number'],
                            'excerpt' => ['type' => 'string'],
                            'publishedAt' => ['type' => ['string', 'null'], 'description' => 'ISO 8601 or null'],
                            'confirmsIncident' => ['type' => 'boolean'],
                        ],
                    ],
                ],
            ],
        ];

        $message = $this->client()->messages->create(
            model: $this->anthropicModel,
            maxTokens: 4096,
            system: 'Odpowiadasz wyłącznie zgodnie z podanym schematem JSON. Cytujesz tylko źródła, które faktycznie odwiedziłeś.',
            messages: [['role' => 'user', 'content' => $prompt]],
            tools: [WebSearchTool20260209::with(maxUses: 6, allowedDomains: self::ALLOWED_DOMAINS, userLocation: ['type' => 'approximate', 'country' => 'PL', 'timezone' => 'Europe/Warsaw'])],
            outputConfig: ['format' => ['type' => 'json_schema', 'schema' => $schema]],
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

        /** @var array{summary?: string, suggestedVerificationQuestion?: string|null, findings?: list<array<string, mixed>>} $data */
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        $findings = [];
        foreach ($data['findings'] ?? [] as $f) {
            $publishedAt = null;
            if (\is_string($f['publishedAt'] ?? null) && '' !== $f['publishedAt']) {
                try {
                    $publishedAt = new DateTimeImmutable($f['publishedAt']);
                } catch (Exception) {
                    $publishedAt = null;
                }
            }
            $findings[] = new ResearchFinding(
                url: (string) ($f['url'] ?? ''),
                title: (string) ($f['title'] ?? ''),
                publisher: (string) ($f['publisher'] ?? ''),
                kind: (string) ($f['kind'] ?? 'other'),
                credibility: (float) ($f['credibility'] ?? 0),
                excerpt: (string) ($f['excerpt'] ?? ''),
                publishedAt: $publishedAt,
                confirmsIncident: (bool) ($f['confirmsIncident'] ?? false),
            );
        }

        return new ResearchResult(
            (string) ($data['summary'] ?? ''),
            $findings,
            isset($data['suggestedVerificationQuestion']) && \is_string($data['suggestedVerificationQuestion']) ? $data['suggestedVerificationQuestion'] : null,
        );
    }

    private function client(): Client
    {
        return $this->client ??= new Client(apiKey: $this->anthropicApiKey);
    }
}
