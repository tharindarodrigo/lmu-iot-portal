<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IoT\DeviceTypes\Pages;

use App\Domain\IoT\Enums\HttpAuthType;
use App\Domain\IoT\Enums\ProtocolType;
use App\Domain\IoT\ProtocolConfigs\HttpProtocolConfig;
use App\Domain\IoT\ProtocolConfigs\MqttProtocolConfig;
use App\Filament\Admin\Resources\IoT\DeviceTypes\DeviceTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeviceType extends EditRecord
{
    protected static string $resource = DeviceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Convert protocol config object to array for form display
        if (isset($data['protocol_config']) && is_object($data['protocol_config'])) {
            $config = $data['protocol_config'];
            $data['protocol_config'] = (array) $config;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['default_protocol']) && isset($data['protocol_config'])) {
            $protocol = ProtocolType::from($data['default_protocol']);
            $config = $data['protocol_config'];

            $data['protocol_config'] = match ($protocol) {
                ProtocolType::Mqtt => new MqttProtocolConfig(
                    brokerHost: $config['broker_host'] ?? '',
                    brokerPort: (int) ($config['broker_port'] ?? 1883),
                    username: $config['username'] ?? null,
                    password: $config['password'] ?? null,
                    useTls: (bool) ($config['use_tls'] ?? false),
                    telemetryTopicTemplate: $config['telemetry_topic_template'] ?? 'device/:device_uuid/data',
                    controlTopicTemplate: $config['command_topic_template'] ?? 'device/:device_uuid/ctrl',
                    qos: (int) ($config['qos'] ?? 1),
                    retain: (bool) ($config['retain'] ?? false),
                ),
                ProtocolType::Http => new HttpProtocolConfig(
                    baseUrl: $config['base_url'] ?? '',
                    telemetryEndpoint: $config['telemetry_endpoint'] ?? '/telemetry',
                    controlEndpoint: $config['command_endpoint'] ?? null,
                    method: $config['method'] ?? 'POST',
                    headers: $config['headers'] ?? [],
                    authType: isset($config['auth_type']) ? HttpAuthType::from($config['auth_type']) : HttpAuthType::None,
                    authToken: $config['auth_token'] ?? null,
                    authUsername: $config['auth_username'] ?? null,
                    authPassword: $config['auth_password'] ?? null,
                    timeout: (int) ($config['timeout'] ?? 30),
                ),
            };
        }

        return $data;
    }
}
