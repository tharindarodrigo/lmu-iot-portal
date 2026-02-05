<?php

declare(strict_types=1);

use App\Domain\IoT\Enums\HttpAuthType;
use App\Domain\IoT\Enums\ProtocolType;
use App\Domain\IoT\Models\DeviceType;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\IoT\DeviceTypes\Pages\CreateDeviceType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->user);
});

it('can render the create device type page', function (): void {
    livewire(CreateDeviceType::class)
        ->assertSuccessful();
});

it('can create a new device type with MQTT protocol', function (): void {
    livewire(CreateDeviceType::class)
        ->fillForm([
            'key' => 'mqtt_energy_meter',
            'name' => 'MQTT Energy Meter',
            'default_protocol' => ProtocolType::Mqtt->value,
            'protocol_config' => [
                'broker_host' => 'mqtt.example.com',
                'broker_port' => 18883,
                'username' => 'test_user',
                'password' => 'test_pass',
                'use_tls' => true,
                'telemetry_topic_template' => 'devices/{device_id}/telemetry',
                'command_topic_template' => 'devices/{device_id}/commands',
                'qos' => 1,
                'retain' => false,
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('device_types', [
        'key' => 'mqtt_energy_meter',
        'name' => 'MQTT Energy Meter',
        'default_protocol' => ProtocolType::Mqtt->value,
    ]);
});

it('can create a new device type with HTTP protocol', function (): void {
    livewire(CreateDeviceType::class)
        ->fillForm([
            'key' => 'http_temp_sensor',
            'name' => 'HTTP Temperature Sensor',
            'default_protocol' => ProtocolType::Http->value,
            'protocol_config' => [
                'base_url' => 'https://api.example.com',
                'telemetry_endpoint' => '/telemetry/{device_id}',
                'command_endpoint' => '/commands/{device_id}',
                'method' => 'POST',
                'auth_type' => HttpAuthType::Bearer->value,
                'auth_token' => 'test_token_123',
                'timeout' => 30,
                'headers' => [],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('device_types', [
        'key' => 'http_temp_sensor',
        'name' => 'HTTP Temperature Sensor',
        'default_protocol' => ProtocolType::Http->value,
    ]);
});

it('validates required key field', function (): void {
    livewire(CreateDeviceType::class)
        ->fillForm([
            'key' => '',
            'name' => 'Test Device',
            'default_protocol' => ProtocolType::Mqtt->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['key' => 'required']);
});

it('validates required name field', function (): void {
    livewire(CreateDeviceType::class)
        ->fillForm([
            'key' => 'test_device',
            'name' => '',
            'default_protocol' => ProtocolType::Mqtt->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('validates required protocol field', function (): void {
    livewire(CreateDeviceType::class)
        ->fillForm([
            'key' => 'test_device',
            'name' => 'Test Device',
            'default_protocol' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['default_protocol' => 'required']);
});

it('validates unique key for global device types', function (): void {
    DeviceType::factory()->global()->create(['key' => 'duplicate_key']);

    livewire(CreateDeviceType::class)
        ->fillForm([
            'key' => 'duplicate_key',
            'name' => 'Duplicate Device',
            'default_protocol' => ProtocolType::Mqtt->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['key' => 'unique']);
});

it('validates key format (lowercase, numbers, underscores only)', function (): void {
    livewire(CreateDeviceType::class)
        ->fillForm([
            'key' => 'Invalid-Key-With-Hyphens',
            'name' => 'Test Device',
            'default_protocol' => ProtocolType::Mqtt->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['key']);
});

it('validates MQTT broker port range', function (): void {
    livewire(CreateDeviceType::class)
        ->fillForm([
            'key' => 'mqtt_device',
            'name' => 'MQTT Device',
            'default_protocol' => ProtocolType::Mqtt->value,
            'protocol_config' => [
                'broker_host' => 'mqtt.example.com',
                'broker_port' => 70000, // Invalid port
                'telemetry_topic_template' => 'test',
                'command_topic_template' => 'test',
                'qos' => 1,
                'retain' => false,
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['protocol_config.broker_port']);
});

it('validates HTTP base URL format', function (): void {
    livewire(CreateDeviceType::class)
        ->fillForm([
            'key' => 'http_device',
            'name' => 'HTTP Device',
            'default_protocol' => ProtocolType::Http->value,
            'protocol_config' => [
                'base_url' => 'not-a-valid-url',
                'telemetry_endpoint' => '/telemetry',
                'command_endpoint' => '/commands',
                'method' => 'POST',
                'auth_type' => HttpAuthType::None->value,
                'timeout' => 30,
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['protocol_config.base_url']);
});
