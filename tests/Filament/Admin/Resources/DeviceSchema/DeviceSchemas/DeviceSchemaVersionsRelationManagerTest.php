<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\DeviceTypeResource;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\Pages\ViewDeviceSchema;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\RelationManagers\DeviceSchemaVersionsRelationManager;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\DeviceSchemaVersionResource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->user);

    $this->deviceType = DeviceType::factory()->mqtt()->create();
    $this->schema = DeviceSchema::factory()->forDeviceType($this->deviceType)->create();

    DeviceSchemaVersionsRelationManager::skipAuthorization();
});

afterEach(function (): void {
    DeviceSchemaVersionsRelationManager::skipAuthorization(false);
});

it('can render the schema versions relation manager', function (): void {
    livewire(DeviceSchemaVersionsRelationManager::class, [
        'ownerRecord' => $this->schema,
        'pageClass' => ViewDeviceSchema::class,
    ])
        ->assertOk();
});

it('lists only schema versions belonging to the owner schema', function (): void {
    $ownerVersions = collect([
        DeviceSchemaVersion::factory()->create([
            'device_schema_id' => $this->schema->id,
            'version' => 2,
        ]),
        DeviceSchemaVersion::factory()->create([
            'device_schema_id' => $this->schema->id,
            'version' => 3,
        ]),
    ]);

    $otherSchema = DeviceSchema::factory()->forDeviceType($this->deviceType)->create();
    $otherVersion = DeviceSchemaVersion::factory()->create([
        'device_schema_id' => $otherSchema->id,
        'version' => 2,
    ]);

    livewire(DeviceSchemaVersionsRelationManager::class, [
        'ownerRecord' => $this->schema,
        'pageClass' => ViewDeviceSchema::class,
    ])
        ->assertCanSeeTableRecords($ownerVersions)
        ->assertCanNotSeeTableRecords([$otherVersion]);
});

it('shows hierarchy traversal links to device type and schema version', function (): void {
    $version = DeviceSchemaVersion::factory()->create([
        'device_schema_id' => $this->schema->id,
    ]);

    livewire(DeviceSchemaVersionsRelationManager::class, [
        'ownerRecord' => $this->schema,
        'pageClass' => ViewDeviceSchema::class,
    ])
        ->assertCanSeeTableRecords([$version])
        ->assertSee(DeviceTypeResource::getUrl('view', ['record' => $this->deviceType->id]))
        ->assertSee(DeviceSchemaVersionResource::getUrl('view', ['record' => $version->id]));
});
