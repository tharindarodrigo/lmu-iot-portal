<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domain\DeviceManagement\Enums\ProtocolType;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\Publishing\Nats\NatsDeviceStateStore;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Throwable;

class NatsControlPanel extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.admin.pages.nats-control-panel';

    public string $natsHost = '127.0.0.1';

    public int $natsPort = 4223;

    public string $deviceUuid = '';

    public string $topic = '';

    public string $payloadJson = '{}';

    /**
     * @var array<int, array{topic: string, payload: array<string, mixed>, stored_at: string}>
     */
    public array $topicStates = [];

    public ?int $deviceTypeId = null;

    public ?string $mqttUsername = null;

    public ?string $mqttPassword = null;

    public string $mqttBaseTopic = 'devices';

    public static function getNavigationGroup(): ?string
    {
        return __('IoT Management');
    }

    public static function getNavigationLabel(): string
    {
        return __('NATS Control Panel');
    }

    public function mount(): void
    {
        $host = config('iot.nats.host', '127.0.0.1');
        $port = config('iot.nats.port', 4223);

        $this->natsHost = is_string($host) && $host !== '' ? $host : '127.0.0.1';
        $this->natsPort = is_numeric($port) ? (int) $port : 4223;
    }

    /**
     * @return array<int, string>
     */
    public function getDeviceTypeOptionsProperty(): array
    {
        return DeviceType::query()
            ->where('default_protocol', ProtocolType::Mqtt->value)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function refreshTopicStates(): void
    {
        if (trim($this->deviceUuid) === '') {
            Notification::make()
                ->title('Device UUID is required')
                ->warning()
                ->send();

            return;
        }

        try {
            $this->topicStates = $this->stateStore()->getAllStates(
                deviceUuid: trim($this->deviceUuid),
                host: trim($this->natsHost),
                port: $this->natsPort,
            );

            Notification::make()
                ->title('NATS KV state loaded')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Unable to load KV state')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function loadTopicPayload(): void
    {
        if (trim($this->deviceUuid) === '' || trim($this->topic) === '') {
            Notification::make()
                ->title('Device UUID and topic are required')
                ->warning()
                ->send();

            return;
        }

        try {
            $state = $this->stateStore()->getStateByTopic(
                deviceUuid: trim($this->deviceUuid),
                topic: trim($this->topic),
                host: trim($this->natsHost),
                port: $this->natsPort,
            );

            if ($state === null) {
                Notification::make()
                    ->title('No topic state found')
                    ->warning()
                    ->send();

                return;
            }

            $this->payloadJson = json_encode($state['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

            Notification::make()
                ->title('Topic payload loaded')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Unable to load topic payload')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function upsertTopicPayload(): void
    {
        /** @var mixed $decodedPayload */
        $decodedPayload = json_decode($this->payloadJson, true);

        if (! is_array($decodedPayload)) {
            Notification::make()
                ->title('Payload must be valid JSON object')
                ->danger()
                ->send();

            return;
        }

        if (trim($this->deviceUuid) === '' || trim($this->topic) === '') {
            Notification::make()
                ->title('Device UUID and topic are required')
                ->warning()
                ->send();

            return;
        }

        try {
            $this->stateStore()->store(
                deviceUuid: trim($this->deviceUuid),
                topic: trim($this->topic),
                payload: $decodedPayload,
                host: trim($this->natsHost),
                port: $this->natsPort,
            );

            $this->refreshTopicStates();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Unable to save topic payload')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function loadMqttCredentials(): void
    {
        if ($this->deviceTypeId === null) {
            Notification::make()
                ->title('Select a device type')
                ->warning()
                ->send();

            return;
        }

        $deviceType = DeviceType::query()->find($this->deviceTypeId);

        if ($deviceType === null || $deviceType->default_protocol !== ProtocolType::Mqtt) {
            Notification::make()
                ->title('Device type must use MQTT')
                ->danger()
                ->send();

            return;
        }

        $config = $deviceType->protocol_config?->toArray() ?? [];

        $this->mqttUsername = Arr::get($config, 'username');
        $this->mqttPassword = Arr::get($config, 'password');
        $this->mqttBaseTopic = (string) Arr::get($config, 'base_topic', 'devices');

        Notification::make()
            ->title('MQTT credentials loaded')
            ->success()
            ->send();
    }

    public function saveMqttCredentials(): void
    {
        if ($this->deviceTypeId === null) {
            Notification::make()
                ->title('Select a device type')
                ->warning()
                ->send();

            return;
        }

        $deviceType = DeviceType::query()->find($this->deviceTypeId);

        if ($deviceType === null || $deviceType->default_protocol !== ProtocolType::Mqtt) {
            Notification::make()
                ->title('Device type must use MQTT')
                ->danger()
                ->send();

            return;
        }

        $config = $deviceType->protocol_config?->toArray() ?? [];
        $config['username'] = $this->mqttUsername;
        $config['password'] = $this->mqttPassword;
        $config['base_topic'] = trim($this->mqttBaseTopic) !== '' ? trim($this->mqttBaseTopic) : 'devices';

        $deviceType->update(['protocol_config' => $config]);

        Notification::make()
            ->title('MQTT credentials saved')
            ->success()
            ->send();
    }

    private function stateStore(): NatsDeviceStateStore
    {
        /** @var NatsDeviceStateStore $store */
        $store = app(NatsDeviceStateStore::class);

        return $store;
    }
}
