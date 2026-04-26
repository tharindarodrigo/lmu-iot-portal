<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\Models\VirtualDeviceLink;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\DeviceManagement\Devices\Pages\EditDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

function editDeviceFormContext(): array
{
    $organization = Organization::factory()->create();
    $deviceType = DeviceType::factory()->global()->create([
        'name' => 'Standard Aggregate Device',
        'key' => 'standard_aggregate_device_edit',
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

it('can update a virtual device and resync its source devices', function (): void {
    ['organization' => $organization, 'deviceType' => $deviceType, 'activeSchemaVersion' => $activeSchemaVersion] = editDeviceFormContext();

    $statusDevice = Device::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Status Sensor',
    ]);
    $energyDevice = Device::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Energy Meter',
    ]);
    $lengthDevice = Device::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Length Counter',
    ]);

    $virtualDevice = Device::factory()->virtual()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $activeSchemaVersion->id,
        'name' => 'Stenter Standard',
    ]);

    $statusLink = VirtualDeviceLink::factory()->create([
        'virtual_device_id' => $virtualDevice->id,
        'source_device_id' => $statusDevice->id,
        'purpose' => 'status',
        'sequence' => 1,
    ]);
    $energyLink = VirtualDeviceLink::factory()->create([
        'virtual_device_id' => $virtualDevice->id,
        'source_device_id' => $energyDevice->id,
        'purpose' => 'energy',
        'sequence' => 2,
    ]);

    livewire(EditDevice::class, ['record' => $virtualDevice->getRouteKey()])
        ->fillForm([
            'name' => 'Stenter Standard v2',
            'organization_id' => $organization->id,
            'device_type_id' => $deviceType->id,
            'device_schema_version_id' => $activeSchemaVersion->id,
            'is_virtual' => true,
            'virtual_device_links' => [
                [
                    'id' => $statusLink->id,
                    'purpose' => 'status',
                    'source_device_id' => $statusDevice->id,
                ],
                [
                    'purpose' => 'length',
                    'source_device_id' => $lengthDevice->id,
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('devices', [
        'id' => $virtualDevice->id,
        'name' => 'Stenter Standard v2',
        'is_virtual' => true,
    ]);

    $this->assertDatabaseHas('virtual_device_links', [
        'id' => $statusLink->id,
        'virtual_device_id' => $virtualDevice->id,
        'source_device_id' => $statusDevice->id,
        'purpose' => 'status',
        'sequence' => 1,
    ]);

    $this->assertDatabaseHas('virtual_device_links', [
        'virtual_device_id' => $virtualDevice->id,
        'source_device_id' => $lengthDevice->id,
        'purpose' => 'length',
        'sequence' => 2,
    ]);

    $this->assertDatabaseMissing('virtual_device_links', [
        'id' => $energyLink->id,
    ]);
});

it('clears virtual source links when a device is switched back to physical', function (): void {
    ['organization' => $organization, 'deviceType' => $deviceType, 'activeSchemaVersion' => $activeSchemaVersion] = editDeviceFormContext();

    $sourceDevice = Device::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Status Sensor',
    ]);
    $hub = Device::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Main Hub',
        'parent_device_id' => null,
    ]);

    $virtualDevice = Device::factory()->virtual()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $activeSchemaVersion->id,
        'name' => 'Convertible Device',
    ]);

    $link = VirtualDeviceLink::factory()->create([
        'virtual_device_id' => $virtualDevice->id,
        'source_device_id' => $sourceDevice->id,
        'purpose' => 'status',
        'sequence' => 1,
    ]);

    livewire(EditDevice::class, ['record' => $virtualDevice->getRouteKey()])
        ->fillForm([
            'name' => 'Convertible Device',
            'organization_id' => $organization->id,
            'device_type_id' => $deviceType->id,
            'device_schema_version_id' => $activeSchemaVersion->id,
            'is_virtual' => false,
            'parent_device_id' => $hub->id,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('devices', [
        'id' => $virtualDevice->id,
        'is_virtual' => false,
        'parent_device_id' => $hub->id,
    ]);

    $this->assertDatabaseMissing('virtual_device_links', [
        'id' => $link->id,
    ]);
});
