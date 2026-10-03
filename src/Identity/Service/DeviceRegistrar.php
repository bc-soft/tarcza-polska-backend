<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Dto\RegisterDeviceRequest;
use App\Identity\Entity\Device;
use App\Identity\Repository\DeviceRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final readonly class DeviceRegistrar
{
    public function __construct(
        private DeviceRepository $devices,
        private JWTTokenManagerInterface $jwt,
    ) {
    }

    /** @return array{device: Device, token: string} */
    public function register(RegisterDeviceRequest $request): array
    {
        $device = new Device();
        $device->setPlatform($request->platform);
        $device->setAppVersion($request->appVersion);
        $this->devices->save($device, true);
        if (null !== $request->pushToken) {
            $this->bindPushToken($device, $request->pushToken);
        }

        return ['device' => $device, 'token' => $this->jwt->create($device)];
    }

    /** Stores the FCM token on this device and takes it away from any other device that still holds it. */
    public function bindPushToken(Device $device, string $pushToken): void
    {
        $device->setPushToken($pushToken);
        $device->touch();
        $this->devices->save($device, true);
        $this->devices->detachPushTokenFromOthers($pushToken, $device);
    }
}
