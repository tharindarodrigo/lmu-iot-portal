<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\Services;

use App\Domain\DeviceManagement\Models\Device;
use App\Events\DeviceConnectionChanged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DevicePresenceService
{
    public function markOnline(Device $device, ?Carbon $seenAt = null): void
    {
        $resolvedSeenAt = $seenAt ?? now();
        $previousState = $device->connection_state;

        $device->updateQuietly([
            'connection_state' => 'online',
            'last_seen_at' => $resolvedSeenAt,
        ]);

        if ($previousState !== 'online') {
            try {
                event(new DeviceConnectionChanged(
                    deviceId: $device->id,
                    deviceUuid: $device->uuid,
                    connectionState: 'online',
                    lastSeenAt: $resolvedSeenAt,
                ));
            } catch (\Throwable $e) {
                Log::channel('device_control')->warning('DeviceConnectionChanged broadcast failed (non-fatal)', [
                    'device_id' => $device->id,
                    'error' => $e->getMessage(),
                ]);
            }

            Log::channel('device_control')->info('Device came online', [
                'device_id' => $device->id,
                'device_uuid' => $device->uuid,
                'previous_state' => $previousState,
            ]);
        }
    }

    public function markOffline(Device $device, ?Carbon $seenAt = null): void
    {
        $previousState = $device->connection_state;

        if ($previousState === 'offline') {
            return;
        }

        $device->updateQuietly([
            'connection_state' => 'offline',
        ]);

        try {
            event(new DeviceConnectionChanged(
                deviceId: $device->id,
                deviceUuid: $device->uuid,
                connectionState: 'offline',
                lastSeenAt: $seenAt ?? ($device->last_seen_at ? Carbon::parse($device->last_seen_at) : null),
            ));
        } catch (\Throwable $e) {
            Log::channel('device_control')->warning('DeviceConnectionChanged broadcast failed (non-fatal)', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::channel('device_control')->info('Device went offline', [
            'device_id' => $device->id,
            'device_uuid' => $device->uuid,
            'previous_state' => $previousState,
        ]);
    }

    public function markOfflineByUuid(string $identifier): void
    {
        $device = $this->resolveDeviceByIdentifier($identifier);

        if ($device === null) {
            Log::channel('device_control')->warning('Presence offline message for unknown device', [
                'identifier' => $identifier,
            ]);

            return;
        }

        $this->markOffline($device);
    }

    public function markOnlineByUuid(string $identifier): void
    {
        $device = $this->resolveDeviceByIdentifier($identifier);

        if ($device === null) {
            Log::channel('device_control')->warning('Presence online message for unknown device', [
                'identifier' => $identifier,
            ]);

            return;
        }

        $this->markOnline($device);
    }

    private function resolveDeviceByIdentifier(string $identifier): ?Device
    {
        if (\Illuminate\Support\Str::isUuid($identifier)) {
            $device = Device::query()->where('uuid', $identifier)->first();

            if ($device !== null) {
                return $device;
            }
        }

        return Device::query()->where('external_id', $identifier)->first();
    }
}
