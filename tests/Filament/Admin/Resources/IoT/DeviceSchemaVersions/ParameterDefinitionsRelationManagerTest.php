<?php

declare(strict_types=1);

use App\Domain\DeviceTypes\Models\DeviceType;
use App\Domain\IoT\Enums\ParameterDataType;
use App\Domain\IoT\Models\DeviceSchema;
use App\Domain\IoT\Models\DeviceSchemaVersion;
use App\Domain\IoT\Models\ParameterDefinition;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\IoT\DeviceSchemaVersions\Pages\EditDeviceSchemaVersion;
use App\Filament\Admin\Resources\IoT\DeviceSchemaVersions\RelationManagers\ParameterDefinitionsRelationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->user);

    ParameterDefinitionsRelationManager::skipAuthorization();
});

afterEach(function (): void {
    ParameterDefinitionsRelationManager::skipAuthorization(false);
});

it('validates unique key per schema version', function (): void {
    $deviceType = DeviceType::factory()->mqtt()->create();
    $schema = DeviceSchema::factory()->forDeviceType($deviceType)->create();
    $version = DeviceSchemaVersion::factory()->create([
        'device_schema_id' => $schema->id,
    ]);

    ParameterDefinition::factory()->create([
        'device_schema_version_id' => $version->id,
        'key' => 'temp_c',
        'type' => ParameterDataType::Decimal,
    ]);

    livewire(ParameterDefinitionsRelationManager::class, [
        'ownerRecord' => $version,
        'pageClass' => EditDeviceSchemaVersion::class,
    ])
        ->callTableAction('create', data: [
            'key' => 'temp_c',
            'label' => 'Temp',
            'json_path' => 'temp_c',
            'type' => ParameterDataType::Decimal->value,
            'required' => true,
            'is_critical' => false,
            'sequence' => 1,
            'is_active' => true,
        ])
        ->assertHasFormErrors(['key' => 'unique']);
});
