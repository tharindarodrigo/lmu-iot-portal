<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Services\DevicePresenceService;
use App\Events\DeviceConnectionChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(DevicePresenceService::class);
});

it('marks a device online and broadcasts when transitioning from offline', function (): void {
    Event::fake([DeviceConnectionChanged::class]);

    $device = Device::factory()->create([
        'connection_state' => 'offline',
        'last_seen_at' => null,
    ]);

    $this->service->markOnline($device);

    $device->refresh();
    expect($device->connection_state)->toBe('online')
        ->and($device->last_seen_at)->not->toBeNull();

    Event::assertDispatched(DeviceConnectionChanged::class, function (DeviceConnectionChanged $event) use ($device): bool {
        return $event->deviceId === $device->id && $event->connectionState === 'online';
    });
});

it('updates last_seen_at without broadcasting when already online', function (): void {
    Event::fake([DeviceConnectionChanged::class]);

    $device = Device::factory()->create([
        'connection_state' => 'online',
        'last_seen_at' => now()->subMinutes(5),
    ]);

    $this->service->markOnline($device);

    $device->refresh();
    expect($device->connection_state)->toBe('online')
        ->and($device->last_seen_at->diffInMinutes(now()))->toBeLessThan(1);

    Event::assertNotDispatched(DeviceConnectionChanged::class);
});

it('marks a device offline and broadcasts when transitioning from online', function (): void {
    Event::fake([DeviceConnectionChanged::class]);

    $device = Device::factory()->create([
        'connection_state' => 'online',
        'last_seen_at' => now()->subMinutes(2),
    ]);

    $this->service->markOffline($device);

    $device->refresh();
    expect($device->connection_state)->toBe('offline');

    Event::assertDispatched(DeviceConnectionChanged::class, function (DeviceConnectionChanged $event) use ($device): bool {
        return $event->deviceId === $device->id && $event->connectionState === 'offline';
    });
});

it('does not broadcast when marking an already offline device as offline', function (): void {
    Event::fake([DeviceConnectionChanged::class]);

    $device = Device::factory()->create([
        'connection_state' => 'offline',
        'last_seen_at' => now()->subHours(1),
    ]);

    $this->service->markOffline($device);

    Event::assertNotDispatched(DeviceConnectionChanged::class);
});

it('marks an unknown state device online and broadcasts', function (): void {
    Event::fake([DeviceConnectionChanged::class]);

    $device = Device::factory()->create([
        'connection_state' => 'unknown',
        'last_seen_at' => null,
    ]);

    $this->service->markOnline($device);

    $device->refresh();
    expect($device->connection_state)->toBe('online');

    Event::assertDispatched(DeviceConnectionChanged::class, 1);
});

it('resolves a device by UUID and marks it online', function (): void {
    Event::fake([DeviceConnectionChanged::class]);

    $device = Device::factory()->create([
        'connection_state' => 'offline',
    ]);

    $this->service->markOnlineByUuid($device->uuid);

    $device->refresh();
    expect($device->connection_state)->toBe('online');

    Event::assertDispatched(DeviceConnectionChanged::class, 1);
});

it('resolves a device by UUID and marks it offline', function (): void {
    Event::fake([DeviceConnectionChanged::class]);

    $device = Device::factory()->create([
        'connection_state' => 'online',
    ]);

    $this->service->markOfflineByUuid($device->uuid);

    $device->refresh();
    expect($device->connection_state)->toBe('offline');

    Event::assertDispatched(DeviceConnectionChanged::class, 1);
});

it('handles unknown UUID gracefully without exceptions', function (): void {
    Event::fake([DeviceConnectionChanged::class]);

    $this->service->markOnlineByUuid('non-existent-uuid');
    $this->service->markOfflineByUuid('non-existent-uuid');

    Event::assertNotDispatched(DeviceConnectionChanged::class);
});

it('resolves a device by external_id and marks it online', function (): void {
    Event::fake([DeviceConnectionChanged::class]);

    $device = Device::factory()->create([
        'external_id' => 'rgb-led-01',
        'connection_state' => 'offline',
    ]);

    $this->service->markOnlineByUuid('rgb-led-01');

    $device->refresh();
    expect($device->connection_state)->toBe('online');

    Event::assertDispatched(DeviceConnectionChanged::class, 1);
});

it('resolves a device by external_id and marks it offline', function (): void {
    Event::fake([DeviceConnectionChanged::class]);

    $device = Device::factory()->create([
        'external_id' => 'rgb-led-01',
        'connection_state' => 'online',
    ]);

    $this->service->markOfflineByUuid('rgb-led-01');

    $device->refresh();
    expect($device->connection_state)->toBe('offline');

    Event::assertDispatched(DeviceConnectionChanged::class, 1);
});

it('prefers UUID over external_id when both could match', function (): void {
    Event::fake([DeviceConnectionChanged::class]);

    $sharedUuid = '00000000-0000-4000-8000-000000000001';

    $deviceByUuid = Device::factory()->create([
        'uuid' => $sharedUuid,
        'external_id' => 'other-id',
        'connection_state' => 'offline',
    ]);

    $deviceByExternal = Device::factory()->create([
        'external_id' => $sharedUuid,
        'connection_state' => 'offline',
    ]);

    $this->service->markOnlineByUuid($sharedUuid);

    $deviceByUuid->refresh();
    $deviceByExternal->refresh();

    expect($deviceByUuid->connection_state)->toBe('online')
        ->and($deviceByExternal->connection_state)->toBe('offline');
});
