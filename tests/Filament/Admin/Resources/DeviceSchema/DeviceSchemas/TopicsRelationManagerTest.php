<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\Pages\ViewDeviceSchema;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\RelationManagers\TopicsRelationManager;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\DeviceSchemaVersionResource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->user);

    $this->deviceType = DeviceType::factory()->mqtt()->create();
    $this->schema = DeviceSchema::factory()->forDeviceType($this->deviceType)->create();

    TopicsRelationManager::skipAuthorization();
});

afterEach(function (): void {
    TopicsRelationManager::skipAuthorization(false);
});

it('can render schema topics relation manager', function (): void {
    livewire(TopicsRelationManager::class, [
        'ownerRecord' => $this->schema,
        'pageClass' => ViewDeviceSchema::class,
    ])
        ->assertOk();
});

it('lists topics across schema versions for the owner schema only', function (): void {
    $ownerVersion = DeviceSchemaVersion::factory()->create([
        'device_schema_id' => $this->schema->id,
    ]);

    $ownerTopics = SchemaVersionTopic::factory()->count(2)->create([
        'device_schema_version_id' => $ownerVersion->id,
    ]);

    $otherType = DeviceType::factory()->mqtt()->create();
    $otherSchema = DeviceSchema::factory()->forDeviceType($otherType)->create();
    $otherVersion = DeviceSchemaVersion::factory()->create([
        'device_schema_id' => $otherSchema->id,
    ]);
    $otherTopic = SchemaVersionTopic::factory()->create([
        'device_schema_version_id' => $otherVersion->id,
    ]);

    livewire(TopicsRelationManager::class, [
        'ownerRecord' => $this->schema,
        'pageClass' => ViewDeviceSchema::class,
    ])
        ->assertCanSeeTableRecords($ownerTopics)
        ->assertCanNotSeeTableRecords([$otherTopic]);
});

it('shows schema version links for traversal', function (): void {
    $version = DeviceSchemaVersion::factory()->create([
        'device_schema_id' => $this->schema->id,
    ]);

    $topic = SchemaVersionTopic::factory()->create([
        'device_schema_version_id' => $version->id,
    ]);

    livewire(TopicsRelationManager::class, [
        'ownerRecord' => $this->schema,
        'pageClass' => ViewDeviceSchema::class,
    ])
        ->assertCanSeeTableRecords([$topic])
        ->assertSee(DeviceSchemaVersionResource::getUrl('view', ['record' => $version->id]));
});
