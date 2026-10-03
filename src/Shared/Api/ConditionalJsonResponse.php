<?php

declare(strict_types=1);

namespace App\Shared\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * JSON response with an ETag derived from the body; answers 304 Not Modified to a matching
 * If-None-Match. For endpoints the app polls (map, pending questions, alerts): the query still
 * runs, but the payload is not transferred again when nothing changed.
 */
final class ConditionalJsonResponse
{
    /** @param array<array-key, mixed> $payload */
    public static function create(Request $request, array $payload): JsonResponse
    {
        $response = new JsonResponse($payload);
        $response->setEtag(hash('xxh3', (string) $response->getContent()));
        $response->setPrivate();
        $response->isNotModified($request);

        return $response;
    }
}
