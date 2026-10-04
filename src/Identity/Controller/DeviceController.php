<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Dto\RegisterDeviceRequest;
use App\Identity\Dto\UpdateLocationRequest;
use App\Identity\Dto\UpdatePreferencesRequest;
use App\Identity\Dto\UpdatePushTokenRequest;
use App\Identity\Entity\Device;
use App\Identity\Enum\LocationSource;
use App\Identity\Service\DeviceRegistrar;
use App\Identity\View\DeviceProfileView;
use App\Shared\Geo\H3;
use App\Shared\Geo\Point;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/devices')]
#[OA\Tag(name: 'Devices')]
final class DeviceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly H3 $h3,
    ) {
    }

    #[Route('', name: 'api_device_register', methods: ['POST'])]
    #[OA\Post(summary: 'Register an anonymous device and obtain a JWT', security: [])]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new \Nelmio\ApiDocBundle\Attribute\Model(type: RegisterDeviceRequest::class)))]
    #[OA\Response(response: 201, description: 'Device created', content: new OA\JsonContent(ref: '#/components/schemas/DeviceRegistered'))]
    #[OA\Response(response: 429, description: 'More than 30 registrations an hour from this IP (error.code: too_many_requests, Retry-After header)')]
    public function register(
        #[MapRequestPayload]
        RegisterDeviceRequest $request,
        Request $httpRequest,
        DeviceRegistrar $registrar,
        RateLimiterFactory $deviceRegistrationLimiter,
    ): JsonResponse {
        $deviceRegistrationLimiter->create($httpRequest->getClientIp() ?? 'unknown')->consume()->ensureAccepted();
        $result = $registrar->register($request);

        return new JsonResponse([
            'deviceId' => $result['device']->getId()->toRfc4122(),
            'token' => $result['token'],
        ], Response::HTTP_CREATED);
    }

    #[Route('/me', name: 'api_device_me', methods: ['GET'])]
    #[OA\Get(summary: 'Current device profile')]
    #[OA\Response(response: 200, description: 'Device profile', content: new OA\JsonContent(ref: '#/components/schemas/DeviceProfile'))]
    public function me(#[CurrentUser] Device $device): JsonResponse
    {
        return new JsonResponse(DeviceProfileView::toArray($device));
    }

    #[Route('/me/location', name: 'api_device_location', methods: ['PUT'])]
    #[OA\Put(summary: 'Update the last known location of the device (no history is kept)')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new \Nelmio\ApiDocBundle\Attribute\Model(type: UpdateLocationRequest::class)))]
    #[OA\Response(response: 200, description: 'Location stored, H3 cell returned', content: new OA\JsonContent(ref: '#/components/schemas/LocationUpdated'))]
    #[OA\Response(response: 429, description: 'More than 30 updates a minute from this device (error.code: too_many_requests, Retry-After header)')]
    public function updateLocation(
        #[CurrentUser]
        Device $device,
        #[MapRequestPayload]
        UpdateLocationRequest $request,
        Request $httpRequest,
        RateLimiterFactory $locationUpdateLimiter,
    ): JsonResponse {
        $locationUpdateLimiter->create($device->getUserIdentifier())->consume()->ensureAccepted();

        $point = new Point($request->lat, $request->lng);
        $device->updateLocation($point, $this->h3->cellFor($point), $request->source ?? LocationSource::Gps);
        $this->em->flush();

        return new JsonResponse(['h3Cell' => $device->getH3Cell()]);
    }

    #[Route('/me/preferences', name: 'api_device_preferences', methods: ['PUT'])]
    #[OA\Put(summary: 'Notification preferences (location_refresh reminders on / off)')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new \Nelmio\ApiDocBundle\Attribute\Model(type: UpdatePreferencesRequest::class)))]
    #[OA\Response(response: 204, description: 'Stored')]
    public function updatePreferences(#[CurrentUser] Device $device, #[MapRequestPayload] UpdatePreferencesRequest $request): Response
    {
        $device->setLocationRefreshEnabled($request->locationRefresh);
        $device->touch();
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/me/push-token', name: 'api_device_push_token', methods: ['PUT'])]
    #[OA\Put(summary: 'Register / rotate the FCM push token')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new \Nelmio\ApiDocBundle\Attribute\Model(type: UpdatePushTokenRequest::class)))]
    #[OA\Response(response: 204, description: 'Stored')]
    public function updatePushToken(#[CurrentUser] Device $device, #[MapRequestPayload] UpdatePushTokenRequest $request, DeviceRegistrar $registrar): Response
    {
        $registrar->bindPushToken($device, $request->pushToken);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
