<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\Pages\ViewDeviceType;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\RelationManagers\SchemaVersionsRelationManager;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\DeviceSchemaResource;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\DeviceSchemaVersionResource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->user);

    $this->deviceType = DeviceType::factory()->mqtt()->create();

    SchemaVersionsRelationManager::skipAuthorization();
});

afterEach(function (): void {
    SchemaVersionsRelationManager::skipAuthorization(false);
});

it('can render the schema versions relation manager', function (): void {
    livewire(SchemaVersionsRelationManager::class, [
        'ownerRecord' => $this->deviceType,
        'pageClass' => ViewDeviceType::class,
    ])
        ->assertOk();
});

it('lists schema versions through schemas for the owner device type only', function (): void {
    $ownerSchema = DeviceSchema::factory()->forDeviceType($this->deviceType)->create();
    $ownerVersions = collect([
        DeviceSchemaVersion::factory()->create([
            'device_schema_id' => $ownerSchema->id,
            'version' => 2,
        ]),
        DeviceSchemaVersion::factory()->create([
            'device_schema_id' => $ownerSchema->id,
            'version' => 3,
        ]),
    ]);

    $otherDeviceType = DeviceType::factory()->mqtt()->create();
    $otherSchema = DeviceSchema::factory()->forDeviceType($otherDeviceType)->create();
    $otherVersion = DeviceSchemaVersion::factory()->create([
        'device_schema_id' => $otherSchema->id,
        'version' => 2,
    ]);

    livewire(SchemaVersionsRelationManager::class, [
        'ownerRecord' => $this->deviceType,
        'pageClass' => ViewDeviceType::class,
    ])
        ->assertCanSeeTableRecords($ownerVersions)
        ->assertCanNotSeeTableRecords([$otherVersion]);
});

it('shows schema and schema version links for traversal', function (): void {
    $schema = DeviceSchema::factory()->forDeviceType($this->deviceType)->create();
    $version = DeviceSchemaVersion::factory()->create([
        'device_schema_id' => $schema->id,
    ]);

    livewire(SchemaVersionsRelationManager::class, [
        'ownerRecord' => $this->deviceType,
        'pageClass' => ViewDeviceType::class,
    ])
        ->assertSee(DeviceSchemaResource::getUrl('view', ['record' => $schema->id]))
        ->assertSee(DeviceSchemaVersionResource::getUrl('view', ['record' => $version->id]));
});
