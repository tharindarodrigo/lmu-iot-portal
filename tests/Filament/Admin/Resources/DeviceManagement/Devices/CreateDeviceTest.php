<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\DeviceManagement\Devices\Pages\CreateDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

function createDeviceFormContext(): array
{
    $organization = Organization::factory()->create();
    $deviceType = DeviceType::factory()->global()->create([
        'name' => 'Standard Aggregate Device',
        'key' => 'standard_aggregate_device',
    ]);
    $deviceSchema = DeviceSchema::factory()->forDeviceType($deviceType)->create([
        'name' => 'Standard Aggregate Contract',
    ]);
    $activeSchemaVersion = DeviceSchemaVersion::factory()->active()->create([
        'device_schema_id' => $deviceSchema->id,
        'version' => 1,
    ]);

    return compact('organization', 'deviceType', 'deviceSchema', 'activeSchemaVersion');
}

beforeEach(function (): void {
    Auth::login(User::factory()->create(['is_super_admin' => true]));
});

it('can render the create device page', function (): void {
    livewire(CreateDevice::class)
        ->assertSuccessful();
});

it('can create a virtual device and attach physical source devices by purpose', function (): void {
    ['organization' => $organization, 'deviceType' => $deviceType, 'activeSchemaVersion' => $activeSchemaVersion] = createDeviceFormContext();

    $statusDevice = Device::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Status Sensor',
    ]);
    $energyDevice = Device::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Energy Meter',
    ]);

    livewire(CreateDevice::class)
        ->fillForm([
            'name' => 'Stenter 01 Standard',
            'organization_id' => $organization->id,
            'device_type_id' => $deviceType->id,
            'device_schema_version_id' => $activeSchemaVersion->id,
            'is_virtual' => true,
            'virtual_device_links' => [
                [
                    'purpose' => 'status',
                    'source_device_id' => $statusDevice->id,
                ],
                [
                    'purpose' => 'energy',
                    'source_device_id' => $energyDevice->id,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $virtualDevice = Device::query()
        ->where('name', 'Stenter 01 Standard')
        ->first();

    expect($virtualDevice)->not->toBeNull()
        ->and($virtualDevice?->isVirtual())->toBeTrue()
        ->and($virtualDevice?->parent_device_id)->toBeNull();

    $this->assertDatabaseHas('virtual_device_links', [
        'virtual_device_id' => $virtualDevice?->id,
        'source_device_id' => $statusDevice->id,
        'purpose' => 'status',
        'sequence' => 1,
    ]);

    $this->assertDatabaseHas('virtual_device_links', [
        'virtual_device_id' => $virtualDevice?->id,
        'source_device_id' => $energyDevice->id,
        'purpose' => 'energy',
        'sequence' => 2,
    ]);
});

it('rejects source devices from another organization when creating a virtual device', function (): void {
    ['organization' => $organization, 'deviceType' => $deviceType, 'activeSchemaVersion' => $activeSchemaVersion] = createDeviceFormContext();

    $outsideDevice = Device::factory()->create();

    livewire(CreateDevice::class)
        ->fillForm([
            'name' => 'Cross Org Standard',
            'organization_id' => $organization->id,
            'device_type_id' => $deviceType->id,
            'device_schema_version_id' => $activeSchemaVersion->id,
            'is_virtual' => true,
            'virtual_device_links' => [[
                'purpose' => 'status',
                'source_device_id' => $outsideDevice->id,
            ]],
        ])
        ->call('create')
        ->assertHasFormErrors(['virtual_device_links.0.source_device_id']);
});

it('can still create a physical device with a parent hub', function (): void {
    ['organization' => $organization, 'deviceType' => $deviceType, 'activeSchemaVersion' => $activeSchemaVersion] = createDeviceFormContext();

    $hub = Device::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Main Hub',
        'parent_device_id' => null,
    ]);

    livewire(CreateDevice::class)
        ->fillForm([
            'name' => 'Pressure Sensor',
            'organization_id' => $organization->id,
            'device_type_id' => $deviceType->id,
            'device_schema_version_id' => $activeSchemaVersion->id,
            'is_virtual' => false,
            'parent_device_id' => $hub->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('devices', [
        'name' => 'Pressure Sensor',
        'organization_id' => $organization->id,
        'parent_device_id' => $hub->id,
        'is_virtual' => false,
    ]);
});
