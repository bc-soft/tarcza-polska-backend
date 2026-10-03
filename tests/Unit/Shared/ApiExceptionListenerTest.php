<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Api\ApiExceptionListener;
use App\Shared\Api\ApiProblemException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\RateLimiter\RateLimit;
use Throwable;

final class ApiExceptionListenerTest extends TestCase
{
    public function testRateLimitBecomes429WithRetryAfter(): void
    {
        $retryAt = new DateTimeImmutable('+90 seconds');
        $response = $this->handle(new RateLimitExceededException(new RateLimit(0, $retryAt, false, 10)));

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame('too_many_requests', $body['error']['code']);
        self::assertIsInt($body['error']['retryAfter']);
        self::assertEqualsWithDelta(90, $body['error']['retryAfter'], 2);
        self::assertSame((string) $body['error']['retryAfter'], $response->headers->get('Retry-After'));
    }

    public function testApiProblemExceptionKeepsItsCode(): void
    {
        $response = $this->handle(new ApiProblemException(Response::HTTP_GONE, 'verification_expired', 'This question has expired'));

        self::assertSame(Response::HTTP_GONE, $response->getStatusCode());
        self::assertSame(
            ['error' => ['code' => 'verification_expired', 'message' => 'This question has expired']],
            $this->decode($response),
        );
    }

    public function testPlainHttpExceptionMapsStatusToCode(): void
    {
        $response = $this->handle(new NotFoundHttpException());

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('not_found', $this->decode($response)['error']['code']);
    }

    public function testIgnoresRequestsOutsideApi(): void
    {
        $event = $this->event(new NotFoundHttpException(), '/command');
        new ApiExceptionListener(new NullLogger())($event);

        self::assertNull($event->getResponse());
    }

    private function handle(Throwable $e): Response
    {
        $event = $this->event($e, '/api/v1/reports');
        new ApiExceptionListener(new NullLogger())($event);
        $response = $event->getResponse();
        self::assertNotNull($response);

        return $response;
    }

    private function event(Throwable $e, string $path): ExceptionEvent
    {
        return new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create($path), HttpKernelInterface::MAIN_REQUEST, $e);
    }

    /** @return array{error: array<string, mixed>} */
    private function decode(Response $response): array
    {
        /** @var array{error: array<string, mixed>} */
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
