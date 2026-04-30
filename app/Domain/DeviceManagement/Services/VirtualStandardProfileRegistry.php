<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\Services;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\ValueObjects\VirtualStandards\VirtualStandardProfile;

class VirtualStandardProfileRegistry
{
    /**
     * @return array<string, VirtualStandardProfile>
     */
    public function all(): array
    {
        $resolvedProfiles = [];

        DeviceType::query()
            ->whereNull('organization_id')
            ->whereNotNull('virtual_standard_profile')
            ->get(['id', 'key', 'virtual_standard_profile'])
            ->each(function (DeviceType $deviceType) use (&$resolvedProfiles): void {
                $profile = $this->profileFromDeviceType($deviceType);

                if ($profile === null) {
                    return;
                }

                $resolvedProfiles[$profile->key] = $profile;
            });

        return $resolvedProfiles;
    }

    public function forDeviceType(DeviceType|string|null $deviceType): ?VirtualStandardProfile
    {
        if ($deviceType instanceof DeviceType) {
            return $this->profileFromDeviceType($deviceType);
        }

        if (! is_string($deviceType) || trim($deviceType) === '') {
            return null;
        }

        $resolvedDeviceType = DeviceType::query()
            ->whereNull('organization_id')
            ->where('key', $deviceType)
            ->first(['id', 'key', 'virtual_standard_profile']);

        return $resolvedDeviceType instanceof DeviceType
            ? $this->profileFromDeviceType($resolvedDeviceType)
            : null;
    }

    private function profileFromDeviceType(DeviceType $deviceType): ?VirtualStandardProfile
    {
        $resolvedDeviceType = $deviceType;

        if (! array_key_exists('virtual_standard_profile', $deviceType->getAttributes())) {
            $freshDeviceType = DeviceType::query()
                ->whereKey($deviceType->getKey())
                ->first(['id', 'key', 'virtual_standard_profile']);

            if (! $freshDeviceType instanceof DeviceType) {
                return null;
            }

            $resolvedDeviceType = $freshDeviceType;
        }

        $profile = $resolvedDeviceType->getAttributeValue('virtual_standard_profile');
        $deviceTypeKey = $resolvedDeviceType->getAttributeValue('key');

        if (! is_array($profile) || ! is_string($deviceTypeKey)) {
            return null;
        }

        /** @var array<string, mixed> $profile */
        return VirtualStandardProfile::fromArray($deviceTypeKey, $profile);
    }

    public function forDeviceTypeId(mixed $deviceTypeId): ?VirtualStandardProfile
    {
        if (! is_numeric($deviceTypeId)) {
            return null;
        }

        $deviceType = DeviceType::query()->find((int) $deviceTypeId, ['id', 'key', 'virtual_standard_profile']);

        return $deviceType instanceof DeviceType
            ? $this->forDeviceType($deviceType)
            : null;
    }

    public function forDevice(?Device $device): ?VirtualStandardProfile
    {
        if (! $device instanceof Device) {
            return null;
        }

        $device->loadMissing('deviceType');

        return $device->deviceType instanceof DeviceType
            ? $this->forDeviceType($device->deviceType)
            : null;
    }

    /**
     * @return array<int, string>
     */
    public function requiredPurposes(VirtualStandardProfile $profile): array
    {
        return $profile->requiredPurposes();
    }

    /**
     * @return array<int, string>
     */
    public function allowedDeviceTypeKeysForPurpose(VirtualStandardProfile $profile, string $purpose): array
    {
        return $profile->allowedDeviceTypeKeysForPurpose($purpose);
    }

    /**
     * @return array<string, mixed>
     */
    public function managedMetadata(VirtualStandardProfile $profile): array
    {
        return $profile->managedMetadata();
    }
}
