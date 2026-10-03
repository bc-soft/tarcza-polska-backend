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
        $device->setPushToken($request->pushToken);
        $this->devices->save($device, true);

        return ['device' => $device, 'token' => $this->jwt->create($device)];
    }
}
