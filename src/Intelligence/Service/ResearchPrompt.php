<?php

declare(strict_types=1);

namespace App\Intelligence\Service;

use App\Incident\Entity\Incident;
use App\Intelligence\Model\ResearchFinding;
use App\Intelligence\Model\ResearchResult;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Provider-agnostic part of the research call: the prompt, the trusted-domain list, the JSON schema
 * of the answer and the parser that turns the JSON into a ResearchResult.
 * Both ClaudeResearcher and OpenAiResearcher use it, so switching vendors never changes what we ask for.
 */
final class ResearchPrompt
{
    /** Domains worth trusting for Polish infrastructure incidents. Extend per region. */
    public const array ALLOWED_DOMAINS = [
        'gov.pl', 'rcb.gov.pl', 'pap.pl', 'enea.pl', 'tauron.pl', 'tauron-dystrybucja.pl', 'pgedystrybucja.pl',
        'energa-operator.pl', 'stoen.pl', 'aquanet.pl', 'mpwik.com.pl', 'gddkia.gov.pl', 'ztm.poznan.pl',
        'poznan.pl', 'tvn24.pl', 'polsatnews.pl', 'rmf24.pl', 'radiozet.pl', 'onet.pl', 'wp.pl', 'interia.pl',
        'gazeta.pl', 'wyborcza.pl', 'epoznan.pl', 'gloswielkopolski.pl', 'x.com',
    ];

    public const string SYSTEM = 'Odpowiadasz wyłącznie zgodnie z podanym schematem JSON. Cytujesz tylko źródła, które faktycznie odwiedziłeś. Nie wymyślasz adresów URL.';

    /**
     * @return list<string>
     */
    public static function allowedDomains(?int $limit = null): array
    {
        return null === $limit ? self::ALLOWED_DOMAINS : \array_slice(self::ALLOWED_DOMAINS, 0, $limit);
    }

    public static function userPrompt(Incident $incident, string $placeName): string
    {
        $t = $incident->tallies();

        return \sprintf(
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
    }

    /**
     * Strict JSON schema (every property required, no additional properties) accepted by both vendors.
     *
     * @return array<string, mixed>
     */
    public static function jsonSchema(): array
    {
        return [
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
    }

    /**
     * @param array<string, mixed> $data decoded JSON matching jsonSchema()
     */
    public static function parse(array $data): ResearchResult
    {
        $findings = [];
        /** @var list<array<string, mixed>> $rawFindings */
        $rawFindings = \is_array($data['findings'] ?? null) ? $data['findings'] : [];
        foreach ($rawFindings as $f) {
            $publishedAt = null;
            if (\is_string($f['publishedAt'] ?? null) && '' !== $f['publishedAt']) {
                try {
                    $publishedAt = new DateTimeImmutable($f['publishedAt']);
                } catch (Exception) {
                    $publishedAt = null;
                }
            }
            $findings[] = new ResearchFinding(
                url: \is_string($f['url'] ?? null) ? $f['url'] : '',
                title: \is_string($f['title'] ?? null) ? $f['title'] : '',
                publisher: \is_string($f['publisher'] ?? null) ? $f['publisher'] : '',
                kind: \is_string($f['kind'] ?? null) ? $f['kind'] : 'other',
                credibility: is_numeric($f['credibility'] ?? null) ? (float) $f['credibility'] : 0.0,
                excerpt: \is_string($f['excerpt'] ?? null) ? $f['excerpt'] : '',
                publishedAt: $publishedAt,
                confirmsIncident: (bool) ($f['confirmsIncident'] ?? false),
            );
        }

        $question = $data['suggestedVerificationQuestion'] ?? null;

        return new ResearchResult(
            \is_string($data['summary'] ?? null) ? $data['summary'] : '',
            $findings,
            \is_string($question) && '' !== $question ? $question : null,
        );
    }
}
