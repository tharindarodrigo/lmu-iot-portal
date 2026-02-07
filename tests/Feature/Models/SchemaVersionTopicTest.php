<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Enums\ProtocolType;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\ValueObjects\Protocol\MqttProtocolConfig;
use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('schema version topic can be created with factory', function (): void {
    $topic = SchemaVersionTopic::factory()->create();

    expect($topic)
        ->toBeInstanceOf(SchemaVersionTopic::class)
        ->id->toBeInt()
        ->key->toBeString()
        ->label->toBeString()
        ->direction->toBeInstanceOf(TopicDirection::class)
        ->suffix->toBeString();
});

test('schema version topic belongs to a schema version', function (): void {
    $version = DeviceSchemaVersion::factory()->create();
    $topic = SchemaVersionTopic::factory()->create([
        'device_schema_version_id' => $version->id,
    ]);

    expect($topic->schemaVersion->id)->toBe($version->id);
});

test('schema version topic has many parameters', function (): void {
    $topic = SchemaVersionTopic::factory()->publish()->create();

    ParameterDefinition::factory()->count(3)->create([
        'schema_version_topic_id' => $topic->id,
    ]);

    expect($topic->parameters)->toHaveCount(3);
});

test('publish factory state sets direction to publish', function (): void {
    $topic = SchemaVersionTopic::factory()->publish()->create();

    expect($topic->direction)->toBe(TopicDirection::Publish)
        ->and($topic->isPublish())->toBeTrue()
        ->and($topic->isSubscribe())->toBeFalse();
});

test('subscribe factory state sets direction to subscribe', function (): void {
    $topic = SchemaVersionTopic::factory()->subscribe()->create();

    expect($topic->direction)->toBe(TopicDirection::Subscribe)
        ->and($topic->isSubscribe())->toBeTrue()
        ->and($topic->isPublish())->toBeFalse();
});

test('resolved topic builds correct mqtt path', function (): void {
    $deviceType = DeviceType::factory()->create([
        'default_protocol' => ProtocolType::Mqtt,
        'protocol_config' => (new MqttProtocolConfig(
            brokerHost: 'mqtt.example.com',
            baseTopic: 'sensors',
        ))->toArray(),
    ]);

    $schema = DeviceSchema::factory()->forDeviceType($deviceType)->create();
    $version = DeviceSchemaVersion::factory()->create([
        'device_schema_id' => $schema->id,
    ]);

    $topic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $version->id,
        'suffix' => 'telemetry',
    ]);

    $device = Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $version->id,
    ]);

    $resolved = $topic->resolvedTopic($device);

    expect($resolved)->toBe("sensors/{$device->uuid}/telemetry");
});

test('unique key constraint per schema version', function (): void {
    $version = DeviceSchemaVersion::factory()->create();

    SchemaVersionTopic::factory()->create([
        'device_schema_version_id' => $version->id,
        'key' => 'telemetry',
        'suffix' => 'telemetry',
    ]);

    expect(fn () => SchemaVersionTopic::factory()->create([
        'device_schema_version_id' => $version->id,
        'key' => 'telemetry',
        'suffix' => 'telemetry_dup',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('unique suffix constraint per schema version', function (): void {
    $version = DeviceSchemaVersion::factory()->create();

    SchemaVersionTopic::factory()->create([
        'device_schema_version_id' => $version->id,
        'key' => 'topic_a',
        'suffix' => 'data',
    ]);

    expect(fn () => SchemaVersionTopic::factory()->create([
        'device_schema_version_id' => $version->id,
        'key' => 'topic_b',
        'suffix' => 'data',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('same key can be used across different schema versions', function (): void {
    $version1 = DeviceSchemaVersion::factory()->create();
    $version2 = DeviceSchemaVersion::factory()->create();

    $topic1 = SchemaVersionTopic::factory()->create([
        'device_schema_version_id' => $version1->id,
        'key' => 'telemetry',
        'suffix' => 'telemetry',
    ]);

    $topic2 = SchemaVersionTopic::factory()->create([
        'device_schema_version_id' => $version2->id,
        'key' => 'telemetry',
        'suffix' => 'telemetry',
    ]);

    expect($topic1->id)->not->toBe($topic2->id)
        ->and($topic1->key)->toBe($topic2->key);
});

test('deleting a topic cascades to its parameters', function (): void {
    $topic = SchemaVersionTopic::factory()->publish()->create();

    ParameterDefinition::factory()->count(3)->create([
        'schema_version_topic_id' => $topic->id,
    ]);

    expect(ParameterDefinition::where('schema_version_topic_id', $topic->id)->count())->toBe(3);

    $topic->delete();

    expect(ParameterDefinition::where('schema_version_topic_id', $topic->id)->count())->toBe(0);
});

test('qos defaults to 1 and retain defaults to false', function (): void {
    $topic = SchemaVersionTopic::factory()->create([
        'qos' => 1,
        'retain' => false,
    ]);

    expect($topic->qos)->toBe(1)
        ->and($topic->retain)->toBeFalse();
});
