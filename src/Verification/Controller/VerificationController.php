<?php

declare(strict_types=1);

namespace App\Verification\Controller;

use App\Identity\Entity\Device;
use App\Shared\Api\ApiProblemException;
use App\Verification\Dto\RespondRequest;
use App\Verification\Entity\VerificationRequest;
use App\Verification\Repository\VerificationRequestRepository;
use App\Verification\Service\VerificationResponder;
use DateTimeImmutable;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/verifications')]
#[OA\Tag(name: 'Verification')]
final class VerificationController
{
    public function __construct(
        private readonly VerificationRequestRepository $requests,
        private readonly VerificationResponder $responder,
    ) {
    }

    #[Route('/pending', name: 'api_verification_pending', methods: ['GET'])]
    #[OA\Get(summary: 'Questions waiting for this device (poll on app foreground; pushes carry the same ids)')]
    #[OA\Response(response: 200, description: 'List of pending questions')]
    public function pending(#[CurrentUser] Device $device): JsonResponse
    {
        return new JsonResponse(array_map(self::toArray(...), $this->requests->findPendingForDevice($device)));
    }

    #[Route('/{id}', name: 'api_verification_show', methods: ['GET'])]
    #[OA\Get(summary: 'One question (e.g. opened from a push)')]
    #[OA\Response(response: 200, description: 'Question')]
    public function show(#[CurrentUser] Device $device, VerificationRequest $request): JsonResponse
    {
        $this->assertOwner($device, $request);

        return new JsonResponse(self::toArray($request));
    }

    #[Route('/{id}/response', name: 'api_verification_respond', methods: ['POST'])]
    #[OA\Post(summary: 'Answer YES / NO / UNKNOWN')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: RespondRequest::class)))]
    #[OA\Response(response: 200, description: 'Recorded; returns the incident the answer fed into')]
    #[OA\Response(response: 409, description: 'Already answered')]
    #[OA\Response(response: 410, description: 'Question expired')]
    public function respond(#[CurrentUser] Device $device, VerificationRequest $request, #[MapRequestPayload] RespondRequest $body): JsonResponse
    {
        $this->assertOwner($device, $request);
        if ($request->isAnswered()) {
            throw new ApiProblemException(Response::HTTP_CONFLICT, 'verification_already_answered', 'This question has already been answered');
        }
        if ($request->getExpiresAt() <= new DateTimeImmutable()) {
            throw new ApiProblemException(Response::HTTP_GONE, 'verification_expired', 'This question has expired');
        }

        $this->responder->respond($request, $body->answer);

        return new JsonResponse([
            'verificationId' => $request->getId()->toRfc4122(),
            'incidentId' => $request->getIncident()->getId()->toRfc4122(),
            'thanks' => 'Dziękujemy. Twoja odpowiedź pomaga wyznaczyć zasięg problemu.',
        ]);
    }

    private function assertOwner(Device $device, VerificationRequest $request): void
    {
        if ($request->getDevice()->getId()->toRfc4122() !== $device->getId()->toRfc4122()) {
            throw new NotFoundHttpException();
        }
    }

    /** @return array<string, mixed> */
    private static function toArray(VerificationRequest $r): array
    {
        return [
            'verificationId' => $r->getId()->toRfc4122(),
            'incidentId' => $r->getIncident()->getId()->toRfc4122(),
            'type' => $r->getIncident()->getType()->value,
            'typeLabel' => $r->getIncident()->getType()->label(),
            'question' => $r->getQuestion(),
            'context' => \sprintf('W Twojej okolicy zgłoszono: %s.', mb_strtolower($r->getIncident()->getType()->label())),
            'options' => ['yes', 'no', 'unknown'],
            'sentAt' => $r->getSentAt()->format(\DATE_ATOM),
            'expiresAt' => $r->getExpiresAt()->format(\DATE_ATOM),
            'answered' => $r->isAnswered(),
        ];
    }
}
