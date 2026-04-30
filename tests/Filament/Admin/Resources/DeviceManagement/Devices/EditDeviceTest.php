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

function editGenericDeviceFormContext(): array
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

function editStenterStandardFormContext(): array
{
    $organization = Organization::factory()->create();
    $deviceType = DeviceType::factory()->global()->create([
        'name' => 'Stenter Line',
        'key' => 'stenter_line',
        'virtual_standard_profile' => [
            'label' => 'Stenter Standard',
            'description' => 'Managed stenter profile',
            'shift_schedule' => [
                'id' => 'teejay_stenter_06_00',
                'label' => 'Teejay 06:00 Shift',
            ],
            'sources' => [
                'status' => [
                    'label' => 'Status',
                    'required' => true,
                    'allowed_device_type_keys' => ['status'],
                ],
                'energy' => [
                    'label' => 'Energy',
                    'required' => true,
                    'allowed_device_type_keys' => ['energy_meter'],
                ],
                'length' => [
                    'label' => 'Length',
                    'required' => true,
                    'allowed_device_type_keys' => ['fabric_length_counter'],
                ],
            ],
        ],
    ]);
    $deviceSchema = DeviceSchema::factory()->forDeviceType($deviceType)->create([
        'name' => 'Stenter Standard Contract',
    ]);
    $activeSchemaVersion = DeviceSchemaVersion::factory()->active()->create([
        'device_schema_id' => $deviceSchema->id,
        'version' => 1,
    ]);

    return compact('organization', 'deviceType', 'deviceSchema', 'activeSchemaVersion');
}

function editSourceDevice(Organization $organization, string $deviceTypeKey, string $deviceTypeName, string $deviceName): Device
{
    $deviceType = DeviceType::query()
        ->whereNull('organization_id')
        ->where('key', $deviceTypeKey)
        ->first();

    if (! $deviceType instanceof DeviceType) {
        $deviceType = DeviceType::factory()->global()->mqtt()->create([
            'key' => $deviceTypeKey,
            'name' => $deviceTypeName,
        ]);
    }

    $deviceSchema = DeviceSchema::query()->firstOrCreate([
        'device_type_id' => $deviceType->id,
        'name' => $deviceTypeName.' Contract',
    ]);
    $activeSchemaVersion = DeviceSchemaVersion::query()->firstOrCreate(
        [
            'device_schema_id' => $deviceSchema->id,
            'version' => 1,
        ],
        [
            'status' => 'active',
        ],
    );

    if ($activeSchemaVersion->status !== 'active') {
        $activeSchemaVersion->update(['status' => 'active']);
    }

    return Device::factory()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $activeSchemaVersion->id,
        'name' => $deviceName,
    ]);
}

beforeEach(function (): void {
    Auth::login(User::factory()->create(['is_super_admin' => true]));
});

it('can update a virtual device and resync its source devices', function (): void {
    ['organization' => $organization, 'deviceType' => $deviceType, 'activeSchemaVersion' => $activeSchemaVersion] = editStenterStandardFormContext();

    $statusDevice = editSourceDevice($organization, 'status', 'Status', 'Status Sensor');
    $energyDevice = editSourceDevice($organization, 'energy_meter', 'Energy Meter', 'Energy Meter');
    $oldLengthDevice = editSourceDevice($organization, 'fabric_length_counter', 'Fabric Length Counter', 'Length Counter 01');
    $newLengthDevice = editSourceDevice($organization, 'fabric_length_counter', 'Fabric Length Counter', 'Length Counter 02');

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
    $lengthLink = VirtualDeviceLink::factory()->create([
        'virtual_device_id' => $virtualDevice->id,
        'source_device_id' => $oldLengthDevice->id,
        'purpose' => 'length',
        'sequence' => 3,
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
                    'id' => $energyLink->id,
                    'purpose' => 'energy',
                    'source_device_id' => $energyDevice->id,
                ],
                [
                    'purpose' => 'length',
                    'source_device_id' => $newLengthDevice->id,
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
        'source_device_id' => $newLengthDevice->id,
        'purpose' => 'length',
        'sequence' => 3,
    ]);

    $this->assertDatabaseMissing('virtual_device_links', [
        'id' => $lengthLink->id,
    ]);

    expect(Device::query()->find($virtualDevice->id)?->metadata)
        ->toMatchArray([
            'virtual_standard_profile_key' => 'stenter_line',
            'virtual_standard_shift_schedule_id' => 'teejay_stenter_06_00',
        ]);
});

it('clears virtual source links when a device is switched back to physical', function (): void {
    ['organization' => $organization, 'deviceType' => $deviceType, 'activeSchemaVersion' => $activeSchemaVersion] = editGenericDeviceFormContext();

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
