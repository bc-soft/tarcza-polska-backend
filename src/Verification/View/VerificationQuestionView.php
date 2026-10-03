<?php

declare(strict_types=1);

namespace App\Verification\View;

use App\Verification\Entity\VerificationRequest;
use App\Verification\Enum\VerificationAnswer;

/** GET /api/v1/verifications/* (OpenAPI: VerificationQuestion, VerificationResult). */
final class VerificationQuestionView
{
    /** @return array<string, mixed> */
    public static function toArray(VerificationRequest $r): array
    {
        return [
            'verificationId' => $r->getId()->toRfc4122(),
            'incidentId' => $r->getIncident()->getId()->toRfc4122(),
            'type' => $r->getIncident()->getType()->value,
            'typeLabel' => $r->getIncident()->getType()->label(),
            'question' => $r->getQuestion(),
            'context' => \sprintf('W Twojej okolicy zgłoszono: %s.', mb_strtolower($r->getIncident()->getType()->label())),
            'options' => array_map(static fn (VerificationAnswer $a) => $a->value, VerificationAnswer::cases()),
            'sentAt' => $r->getSentAt()->format(\DATE_ATOM),
            'expiresAt' => $r->getExpiresAt()->format(\DATE_ATOM),
            'answered' => $r->isAnswered(),
        ];
    }

    /** @return array<string, mixed> */
    public static function result(VerificationRequest $r): array
    {
        return [
            'verificationId' => $r->getId()->toRfc4122(),
            'incidentId' => $r->getIncident()->getId()->toRfc4122(),
            'thanks' => 'Dziękujemy. Twoja odpowiedź pomaga wyznaczyć zasięg problemu.',
        ];
    }
}
