<?php

declare(strict_types=1);

namespace App\Shared\Api\OpenApi;

use Nelmio\ApiDocBundle\Describer\DescriberInterface;
use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use OpenApi\Annotations as OA;
use OpenApi\Generator;

/**
 * Documents the error envelope on every /api operation without repeating attributes in controllers:
 * 401 for secured operations, 403 for the Command API, 404 when the path has an id, 422 when there
 * is a request body, and the ErrorResponse schema on every 4xx/5xx that has no content of its own.
 */
final class ErrorResponsesDescriber implements DescriberInterface
{
    private const string ERROR_SCHEMA = '#/components/schemas/ErrorResponse';

    public function describe(OA\OpenApi $api): void
    {
        if (!\is_array($api->paths)) {
            return;
        }

        foreach ($api->paths as $pathItem) {
            $path = $pathItem->path;
            if (!\is_string($path) || !str_starts_with($path, '/api/')) {
                continue;
            }
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                $operation = $pathItem->{$method};
                if (!$operation instanceof OA\Operation) {
                    continue;
                }
                $this->describeOperation($operation, $path);
            }
        }
    }

    private function describeOperation(OA\Operation $operation, string $path): void
    {
        if ([] !== $operation->security) {
            $this->ensure($operation, 401, 'Missing, invalid or expired bearer token (error.code: unauthorized | token_expired)');
        }
        if (str_starts_with($path, '/api/command')) {
            $this->ensure($operation, 403, 'Insufficient role (error.code: forbidden)');
        }
        if (str_contains($path, '{')) {
            $this->ensure($operation, 404, 'Unknown id, or a resource that belongs to another device (error.code: not_found)');
        }
        if (Generator::UNDEFINED !== $operation->requestBody) {
            $this->ensure($operation, 422, 'Invalid body; error.violations[].field names the offending field (error.code: validation_failed)');
        }

        if (!\is_array($operation->responses)) {
            return;
        }
        foreach ($operation->responses as $response) {
            if ((int) $response->response >= 400 && Generator::UNDEFINED === $response->content) {
                $mediaType = Util::createChild($response, OA\MediaType::class, ['mediaType' => 'application/json']);
                $mediaType->schema = Util::createChild($mediaType, OA\Schema::class, ['ref' => self::ERROR_SCHEMA]);
                $response->content = [$mediaType];
            }
        }
    }

    private function ensure(OA\Operation $operation, int $status, string $description): void
    {
        $response = Util::getIndexedCollectionItem($operation, OA\Response::class, $status);
        if (Generator::UNDEFINED === $response->description) {
            $response->description = $description;
        }
    }
}
