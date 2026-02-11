<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Events\DeviceConnectionChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('marks stale online devices as offline and broadcasts events', function (): void {
    Event::fake([DeviceConnectionChanged::class]);

    $staleDevice = Device::factory()->create([
        'connection_state' => 'online',
        'last_seen_at' => now()->subMinutes(10),
    ]);

    $recentDevice = Device::factory()->create([
        'connection_state' => 'online',
        'last_seen_at' => now()->subSeconds(30),
    ]);

    $alreadyOffline = Device::factory()->create([
        'connection_state' => 'offline',
        'last_seen_at' => now()->subHours(1),
    ]);

    $this->artisan('iot:check-device-health', ['--seconds' => 300])
        ->assertExitCode(0);

    $staleDevice->refresh();
    $recentDevice->refresh();
    $alreadyOffline->refresh();

    expect($staleDevice->connection_state)->toBe('offline')
        ->and($recentDevice->connection_state)->toBe('online')
        ->and($alreadyOffline->connection_state)->toBe('offline');

    Event::assertDispatched(DeviceConnectionChanged::class, 1);

    Event::assertDispatched(DeviceConnectionChanged::class, function (DeviceConnectionChanged $event) use ($staleDevice): bool {
        return $event->deviceId === $staleDevice->id && $event->connectionState === 'offline';
    });
});

it('marks devices with null last_seen_at as offline', function (): void {
    Event::fake([DeviceConnectionChanged::class]);

    $device = Device::factory()->create([
        'connection_state' => 'online',
        'last_seen_at' => null,
    ]);

    $this->artisan('iot:check-device-health', ['--seconds' => 300])
        ->assertExitCode(0);

    $device->refresh();
    expect($device->connection_state)->toBe('offline');

    Event::assertDispatched(DeviceConnectionChanged::class, 1);
});

it('does not mark recently seen devices as offline', function (): void {
    Event::fake([DeviceConnectionChanged::class]);

    $device = Device::factory()->create([
        'connection_state' => 'online',
        'last_seen_at' => now(),
    ]);

    $this->artisan('iot:check-device-health', ['--seconds' => 300])
        ->assertExitCode(0);

    $device->refresh();
    expect($device->connection_state)->toBe('online');

    Event::assertNotDispatched(DeviceConnectionChanged::class);
});
