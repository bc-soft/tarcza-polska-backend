<?php

declare(strict_types=1);

namespace App\Shared\Api;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Uniform JSON error envelope for everything under /api. Runs after the security listener (priority 1).
 * { "error": { "code": "validation_failed", "message": "...", "violations": [...] } }.
 */
#[AsEventListener(event: 'kernel.exception', priority: -10)]
final readonly class ApiExceptionListener
{
    public function __construct(
        private LoggerInterface $logger,
        private bool $debug = false,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $e = $event->getThrowable();
        if ($e instanceof AuthenticationException || $e instanceof AccessDeniedException) {
            return; // handled by the security firewall (401 / 403 with proper headers)
        }
        $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        $code = 'internal_error';
        $message = 'Internal server error';
        $violations = [];

        $validation = $e instanceof ValidationFailedException ? $e : ($e->getPrevious() instanceof ValidationFailedException ? $e->getPrevious() : null);

        if (null !== $validation) {
            $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            $code = 'validation_failed';
            $message = 'Request payload is invalid';
            foreach ($validation->getViolations() as $violation) {
                $violations[] = ['field' => $violation->getPropertyPath(), 'message' => (string) $violation->getMessage()];
            }
        } elseif ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $code = match ($status) {
                400 => 'bad_request',
                401 => 'unauthorized',
                403 => 'forbidden',
                404 => 'not_found',
                409 => 'conflict',
                429 => 'too_many_requests',
                default => 'http_error',
            };
            $message = $e->getMessage() ?: Response::$statusTexts[$status] ?? $message;
        } else {
            $this->logger->error('Unhandled API exception', ['exception' => $e]);
            if ($this->debug) {
                $message = $e->getMessage();
            }
        }

        $body = ['error' => ['code' => $code, 'message' => $message]];
        if ([] !== $violations) {
            $body['error']['violations'] = $violations;
        }

        $response = new JsonResponse($body, $status);
        if ($e instanceof HttpExceptionInterface) {
            $response->headers->add($e->getHeaders());
        }
        $event->setResponse($response);
    }
}
