<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Enums\MetricUnit;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\DerivedParameterDefinition;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\Pages\EditDeviceType;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\Pages\ViewDeviceType;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\RelationManagers\DeviceSchemasRelationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->user);

    $this->deviceType = DeviceType::factory()->mqtt()->create();

    DeviceSchemasRelationManager::skipAuthorization();
});

afterEach(function (): void {
    DeviceSchemasRelationManager::skipAuthorization(false);
});

it('can render the device schemas relation manager', function (): void {
    livewire(DeviceSchemasRelationManager::class, [
        'ownerRecord' => $this->deviceType,
        'pageClass' => ViewDeviceType::class,
    ])
        ->assertOk();
});

it('is rendered on the view device type page', function (): void {
    livewire(ViewDeviceType::class, [
        'record' => $this->deviceType->id,
    ])
        ->assertSeeLivewire(DeviceSchemasRelationManager::class);
});

it('can list existing schemas', function (): void {
    $schemas = DeviceSchema::factory()->count(3)->forDeviceType($this->deviceType)->create();

    livewire(DeviceSchemasRelationManager::class, [
        'ownerRecord' => $this->deviceType,
        'pageClass' => ViewDeviceType::class,
    ])
        ->assertCanSeeTableRecords($schemas);
});

it('does not show schemas from other device types', function (): void {
    $otherDeviceType = DeviceType::factory()->mqtt()->create();
    $otherSchema = DeviceSchema::factory()->forDeviceType($otherDeviceType)->create();
    $ownSchema = DeviceSchema::factory()->forDeviceType($this->deviceType)->create();

    livewire(DeviceSchemasRelationManager::class, [
        'ownerRecord' => $this->deviceType,
        'pageClass' => ViewDeviceType::class,
    ])
        ->assertCanSeeTableRecords([$ownSchema])
        ->assertCanNotSeeTableRecords([$otherSchema]);
});

it('can create a schema', function (): void {
    livewire(DeviceSchemasRelationManager::class, [
        'ownerRecord' => $this->deviceType,
        'pageClass' => EditDeviceType::class,
    ])
        ->callTableAction('create', data: [
            'name' => 'Thermal Sensor Contract',
        ])
        ->assertHasNoFormErrors();

    expect(DeviceSchema::where([
        'device_type_id' => $this->deviceType->id,
        'name' => 'Thermal Sensor Contract',
    ])->exists())->toBeTrue();
});

it('validates name is required', function (): void {
    livewire(DeviceSchemasRelationManager::class, [
        'ownerRecord' => $this->deviceType,
        'pageClass' => EditDeviceType::class,
    ])
        ->callTableAction('create', data: [
            'name' => null,
        ])
        ->assertHasFormErrors(['name' => 'required']);
});

it('can search schemas by name', function (): void {
    $schema1 = DeviceSchema::factory()->forDeviceType($this->deviceType)->create(['name' => 'Thermal Contract']);
    $schema2 = DeviceSchema::factory()->forDeviceType($this->deviceType)->create(['name' => 'Network Contract']);

    livewire(DeviceSchemasRelationManager::class, [
        'ownerRecord' => $this->deviceType,
        'pageClass' => ViewDeviceType::class,
    ])
        ->searchTable('Thermal')
        ->assertCanSeeTableRecords([$schema1])
        ->assertCanNotSeeTableRecords([$schema2]);
});

it('creates an initial active schema version during onboarding by default', function (): void {
    livewire(DeviceSchemasRelationManager::class, [
        'ownerRecord' => $this->deviceType,
        'pageClass' => EditDeviceType::class,
    ])
        ->callTableAction('create', data: [
            'name' => 'RGB Contract',
        ])
        ->assertHasNoFormErrors();

    $schema = DeviceSchema::query()
        ->where('device_type_id', $this->deviceType->id)
        ->where('name', 'RGB Contract')
        ->first();

    expect($schema)->not->toBeNull()
        ->and($schema?->versions()->where('version', 1)->where('status', 'active')->exists())->toBeTrue();
});

it('shows explore action data grouped by version, topic, and derived parameter details', function (): void {
    $schema = DeviceSchema::factory()
        ->forDeviceType($this->deviceType)
        ->create(['name' => 'Energy Meter Contract']);

    $schema->versions()->create([
        'version' => 1,
        'status' => 'active',
        'notes' => 'Baseline production schema.',
    ]);

    $latestVersion = $schema->versions()->create([
        'version' => 2,
        'status' => 'draft',
        'notes' => 'Adds command controls and computed metrics.',
    ]);

    $telemetryTopic = SchemaVersionTopic::factory()
        ->publish()
        ->create([
            'device_schema_version_id' => $latestVersion->id,
            'key' => 'telemetry',
            'label' => 'Telemetry',
            'suffix' => 'telemetry',
            'sequence' => 1,
        ]);

    $commandTopic = SchemaVersionTopic::factory()
        ->subscribe()
        ->create([
            'device_schema_version_id' => $latestVersion->id,
            'key' => 'control',
            'label' => 'Control',
            'suffix' => 'control',
            'sequence' => 2,
        ]);

    ParameterDefinition::factory()
        ->for($telemetryTopic, 'topic')
        ->create([
            'key' => 'voltage',
            'label' => 'Voltage',
            'json_path' => '$.voltage',
            'type' => ParameterDataType::Decimal,
            'unit' => MetricUnit::Volts->value,
            'required' => true,
            'is_active' => true,
            'default_value' => null,
            'sequence' => 1,
        ]);

    ParameterDefinition::factory()
        ->subscribe()
        ->for($commandTopic, 'topic')
        ->create([
            'key' => 'sampling_interval',
            'label' => 'Sampling Interval',
            'json_path' => 'sampling.interval',
            'type' => ParameterDataType::Integer,
            'default_value' => 60,
            'required' => true,
            'is_active' => true,
            'sequence' => 1,
        ]);

    DerivedParameterDefinition::factory()->create([
        'device_schema_version_id' => $latestVersion->id,
        'key' => 'power_avg',
        'label' => 'Average Power',
        'data_type' => ParameterDataType::Decimal,
        'unit' => MetricUnit::Watts->value,
        'dependencies' => ['voltage'],
        'expression' => ['var' => 'voltage'],
        'json_path' => 'computed.power_avg',
    ]);

    livewire(DeviceSchemasRelationManager::class, [
        'ownerRecord' => $this->deviceType,
        'pageClass' => ViewDeviceType::class,
    ])
        ->mountTableAction('explore', $schema)
        ->assertTableActionDataSet(fn (array $data): bool => $data['schema_name'] === 'Energy Meter Contract'
            && $data['versions_total'] === 2
            && $data['active_versions_total'] === 1
            && $data['parameter_total'] === 2
            && $data['derived_parameter_total'] === 1
            && $data['versions'][0]['version_number'] === 'v2'
            && $data['versions'][0]['topics'][0]['label'] === 'Telemetry'
            && $data['versions'][0]['topics'][0]['parameters'][0]['key'] === 'voltage'
            && $data['versions'][0]['topics'][1]['parameters'][0]['default_value_preview'] === '60'
            && $data['versions'][0]['derived_parameters'][0]['dependencies_label'] === 'voltage'
            && $data['versions'][0]['derived_parameters'][0]['expression'] === json_encode(['var' => 'voltage'], JSON_PRETTY_PRINT));
});
