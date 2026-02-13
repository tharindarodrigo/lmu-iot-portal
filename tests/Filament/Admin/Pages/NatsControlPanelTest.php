<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Enums\ProtocolType;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\Publishing\Nats\NatsDeviceStateStore;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Pages\NatsControlPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bindFakeNatsStateStoreForPanel(): void
{
    $store = new class implements NatsDeviceStateStore
    {
        /** @var array<string, array<string, array{topic: string, payload: array<string, mixed>, stored_at: string}>> */
        private array $states = [];

        public function store(string $deviceUuid, string $topic, array $payload, string $host = '127.0.0.1', int $port = 4223): void
        {
            $this->states[$deviceUuid][$topic] = [
                'topic' => $topic,
                'payload' => $payload,
                'stored_at' => now()->toIso8601String(),
            ];
        }

        public function getLastState(string $deviceUuid, string $host = '127.0.0.1', int $port = 4223): ?array
        {
            $states = array_values($this->states[$deviceUuid] ?? []);

            return $states[0] ?? null;
        }

        public function getAllStates(string $deviceUuid, string $host = '127.0.0.1', int $port = 4223): array
        {
            return array_values($this->states[$deviceUuid] ?? []);
        }

        public function getStateByTopic(string $deviceUuid, string $topic, string $host = '127.0.0.1', int $port = 4223): ?array
        {
            return $this->states[$deviceUuid][$topic] ?? null;
        }
    };

    app()->instance(NatsDeviceStateStore::class, $store);
}

beforeEach(function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($admin);

    bindFakeNatsStateStoreForPanel();
});

it('can render the nats control panel page', function (): void {
    livewire(NatsControlPanel::class)
        ->assertSuccessful()
        ->assertSee('NATS Key Value Topics')
        ->assertSee('Device MQTT Credentials');
});

it('can upsert and load topic payload from the nats kv state store', function (): void {
    livewire(NatsControlPanel::class)
        ->set('deviceUuid', 'device-001')
        ->set('topic', 'devices.device-001.status')
        ->set('payloadJson', json_encode(['power' => 'on']))
        ->call('upsertTopicPayload')
        ->assertSet('topicStates.0.topic', 'devices.device-001.status')
        ->call('loadTopicPayload')
        ->assertSet('payloadJson', "{\n    \"power\": \"on\"\n}");
});

it('can load and save mqtt credentials for a device type', function (): void {
    $deviceType = DeviceType::factory()->mqtt()->create([
        'default_protocol' => ProtocolType::Mqtt,
        'protocol_config' => [
            'broker_host' => 'localhost',
            'broker_port' => 1883,
            'username' => 'old-user',
            'password' => 'old-pass',
            'use_tls' => false,
            'base_topic' => 'devices',
        ],
    ]);

    livewire(NatsControlPanel::class)
        ->set('deviceTypeId', $deviceType->id)
        ->call('loadMqttCredentials')
        ->assertSet('mqttUsername', 'old-user')
        ->assertSet('mqttPassword', 'old-pass')
        ->set('mqttUsername', 'new-user')
        ->set('mqttPassword', 'new-pass')
        ->set('mqttBaseTopic', 'fleet')
        ->call('saveMqttCredentials');

    expect($deviceType->refresh()->protocol_config?->toArray())
        ->toMatchArray([
            'username' => 'new-user',
            'password' => 'new-pass',
            'base_topic' => 'fleet',
        ]);
});
