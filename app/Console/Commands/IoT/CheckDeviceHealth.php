<?php

declare(strict_types=1);

namespace App\Console\Commands\IoT;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Services\DevicePresenceService;
use Illuminate\Console\Command;

class CheckDeviceHealth extends Command
{
    protected $signature = 'iot:check-device-health
                            {--seconds= : Heartbeat timeout threshold in seconds}';

    protected $description = 'Mark devices as offline if they have not sent telemetry within the heartbeat timeout';

    public function handle(DevicePresenceService $presenceService): int
    {
        $secondsOption = $this->option('seconds');
        $configuredTimeout = config('iot.presence.heartbeat_timeout_seconds', 300);
        $fallbackTimeout = is_numeric($configuredTimeout) ? (int) $configuredTimeout : 300;
        $seconds = is_numeric($secondsOption) ? (int) $secondsOption : $fallbackTimeout;

        $timeoutCutoff = now()->subSeconds(max(1, $seconds));
        $markedOffline = 0;

        Device::query()
            ->where('connection_state', 'online')
            ->where(function ($query) use ($timeoutCutoff): void {
                $query->where('last_seen_at', '<=', $timeoutCutoff)
                    ->orWhereNull('last_seen_at');
            })
            ->orderBy('id')
            ->chunkById(100, function ($devices) use (&$markedOffline, $presenceService): void {
                foreach ($devices as $device) {
                    $presenceService->markOffline($device);
                    $markedOffline++;
                }
            });

        $this->info("Marked {$markedOffline} device(s) offline (heartbeat timeout: {$seconds}s, cutoff: {$timeoutCutoff->toIso8601String()}).");

        return self::SUCCESS;
    }
}
