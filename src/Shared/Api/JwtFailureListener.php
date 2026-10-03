<?php

declare(strict_types=1);

namespace App\Shared\Api;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the 401 answers of the JWT firewalls into the same envelope as ApiExceptionListener:
 * `unauthorized` (missing / invalid token) or `token_expired` (re-register / refresh the device).
 */
final class JwtFailureListener
{
    #[AsEventListener(event: Events::JWT_NOT_FOUND)]
    public function onNotFound(JWTNotFoundEvent $event): void
    {
        $event->setResponse(self::response('unauthorized', 'Missing bearer token'));
    }

    #[AsEventListener(event: Events::JWT_INVALID)]
    public function onInvalid(JWTInvalidEvent $event): void
    {
        $event->setResponse(self::response('unauthorized', 'Invalid bearer token'));
    }

    #[AsEventListener(event: Events::JWT_EXPIRED)]
    public function onExpired(JWTExpiredEvent $event): void
    {
        $event->setResponse(self::response('token_expired', 'Bearer token has expired'));
    }

    private static function response(string $code, string $message): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => $code, 'message' => $message]],
            Response::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => 'Bearer'],
        );
    }
}
