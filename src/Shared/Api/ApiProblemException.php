<?php

declare(strict_types=1);

namespace App\Shared\Api;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * HTTP exception with a stable, machine-readable `error.code` (e.g. verification_expired).
 * Use it when the HTTP status alone is ambiguous for the client.
 */
final class ApiProblemException extends HttpException
{
    /** @param array<string, string> $headers */
    public function __construct(
        int $statusCode,
        private readonly string $errorCode,
        string $message = '',
        array $headers = [],
    ) {
        parent::__construct($statusCode, $message, null, $headers);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
