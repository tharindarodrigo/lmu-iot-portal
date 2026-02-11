<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class DeviceConnectionChanged implements ShouldBroadcastNow
{
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $deviceId,
        public string $deviceUuid,
        public string $connectionState,
        public ?Carbon $lastSeenAt = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('devices'),
            new Channel("device-control.{$this->deviceUuid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'device.connection.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'device_id' => $this->deviceId,
            'device_uuid' => $this->deviceUuid,
            'connection_state' => $this->connectionState,
            'last_seen_at' => ($this->lastSeenAt ?? Carbon::now())->toIso8601String(),
        ];
    }
}
