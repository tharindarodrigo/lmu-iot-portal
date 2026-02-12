<?php

declare(strict_types=1);

namespace App\Domain\DeviceControl\Services;

use App\Domain\DeviceControl\Enums\CommandStatus;
use App\Domain\DeviceControl\Models\DeviceCommandLog;
use App\Domain\DeviceControl\Models\DeviceDesiredTopicState;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Publishing\Nats\NatsPublisherFactory;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Events\CommandDispatched;
use App\Events\CommandSent;
use Illuminate\Log\LogManager;
use Illuminate\Support\Str;

final readonly class DeviceCommandDispatcher
{
    public function __construct(
        private NatsPublisherFactory $natsPublisherFactory,
        private LogManager $logManager,
    ) {}

    /**
     * Dispatch a command from the platform to a device via native NATS.
     *
     * Publishes directly to the NATS subject (e.g. devices.rgb-led-01.control)
     * so the NATS MQTT bridge delivers to subscribed MQTT devices without
     * creating any MQTT session state that could corrupt $MQTT_sess.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(
        Device $device,
        SchemaVersionTopic $topic,
        array $payload,
        ?int $userId = null,
        ?string $host = null,
        ?int $port = null,
    ): DeviceCommandLog {
        $device->loadMissing('deviceType');
        $correlationId = (string) Str::uuid();
        $payloadForPublishing = $this->injectCorrelationMeta($payload, $correlationId);
        $resolvedHost = $this->resolveNatsHost($host);
        $resolvedPort = $this->resolveNatsPort($port);
        $mqttTopic = $this->resolveTopicWithExternalId($device, $topic);
        $natsSubject = str_replace('/', '.', $mqttTopic);

        $this->log()->info('Dispatching command', [
            'device_id' => $device->id,
            'device_external_id' => $device->external_id,
            'device_uuid' => $device->uuid,
            'topic_id' => $topic->id,
            'topic_suffix' => $topic->suffix,
            'nats_subject' => $natsSubject,
            'mqtt_topic' => $mqttTopic,
            'nats_host' => $resolvedHost,
            'nats_port' => $resolvedPort,
            'correlation_id' => $correlationId,
            'payload' => $payload,
            'user_id' => $userId,
        ]);

        $commandLog = DeviceCommandLog::create([
            'device_id' => $device->id,
            'schema_version_topic_id' => $topic->id,
            'user_id' => $userId,
            'command_payload' => $payload,
            'correlation_id' => $correlationId,
            'status' => CommandStatus::Pending,
        ]);

        DeviceDesiredTopicState::updateOrCreate(
            [
                'device_id' => $device->id,
                'schema_version_topic_id' => $topic->id,
            ],
            [
                'desired_payload' => $payload,
                'correlation_id' => $correlationId,
                'reconciled_at' => null,
            ],
        );

        $commandLog->load('device', 'topic');

        $this->log()->debug('Broadcasting CommandDispatched', [
            'command_log_id' => $commandLog->id,
            'correlation_id' => $correlationId,
        ]);

        try {
            event(new CommandDispatched($commandLog));
        } catch (\Throwable $broadcastException) {
            $this->log()->warning('CommandDispatched broadcast failed (non-fatal)', [
                'command_log_id' => $commandLog->id,
                'error' => $broadcastException->getMessage(),
            ]);
        }

        $encodedPayload = json_encode($payloadForPublishing);
        $encodedPayload = is_string($encodedPayload) ? $encodedPayload : '{}';

        try {
            $this->log()->info('Publishing command via native NATS', [
                'command_log_id' => $commandLog->id,
                'nats_subject' => $natsSubject,
                'nats_host' => $resolvedHost,
                'nats_port' => $resolvedPort,
                'payload_size' => strlen($encodedPayload),
            ]);

            $publisher = $this->natsPublisherFactory->make($resolvedHost, $resolvedPort);
            $publisher->publish($natsSubject, $encodedPayload);

            $commandLog->update([
                'status' => CommandStatus::Sent,
                'sent_at' => now(),
            ]);

            $commandLog->refresh();

            $this->log()->info('Command sent successfully via NATS', [
                'command_log_id' => $commandLog->id,
                'correlation_id' => $correlationId,
                'nats_subject' => $natsSubject,
                'sent_at' => (string) $commandLog->sent_at,
            ]);

            $this->log()->debug('Broadcasting CommandSent', [
                'command_log_id' => $commandLog->id,
                'nats_subject' => $natsSubject,
            ]);

            try {
                event(new CommandSent($commandLog, $natsSubject));
            } catch (\Throwable $broadcastException) {
                $this->log()->warning('CommandSent broadcast failed (non-fatal)', [
                    'command_log_id' => $commandLog->id,
                    'error' => $broadcastException->getMessage(),
                ]);
            }
        } catch (\Throwable $exception) {
            $this->log()->error('Command publish failed', [
                'command_log_id' => $commandLog->id,
                'correlation_id' => $correlationId,
                'nats_subject' => $natsSubject,
                'nats_host' => $resolvedHost,
                'nats_port' => $resolvedPort,
                'error' => $exception->getMessage(),
            ]);

            $commandLog->update([
                'status' => CommandStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);

            report($exception);
        }

        return $commandLog;
    }

    private function resolveNatsHost(?string $host): string
    {
        if (is_string($host) && trim($host) !== '') {
            return trim($host);
        }

        $configuredHost = config('iot.nats.host', '127.0.0.1');

        return is_string($configuredHost) && trim($configuredHost) !== ''
            ? trim($configuredHost)
            : '127.0.0.1';
    }

    private function resolveNatsPort(?int $port): int
    {
        if (is_int($port) && $port > 0) {
            return $port;
        }

        $configuredPort = config('iot.nats.port', 4223);

        return is_numeric($configuredPort) ? (int) $configuredPort : 4223;
    }

    private function resolveTopicWithExternalId(Device $device, SchemaVersionTopic $topic): string
    {
        $baseTopic = $device->deviceType?->protocol_config?->getBaseTopic() ?? 'device';
        $identifier = $device->external_id ?: $device->uuid;

        return trim($baseTopic, '/').'/'.$identifier.'/'.$topic->suffix;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function injectCorrelationMeta(array $payload, string $correlationId): array
    {
        if (! (bool) config('iot.device_control.inject_meta_command_id', true)) {
            return $payload;
        }

        $meta = $payload['_meta'] ?? [];

        if (! is_array($meta)) {
            $meta = [];
        }

        $meta['command_id'] = $correlationId;
        $payload['_meta'] = $meta;

        return $payload;
    }

    private function log(): \Psr\Log\LoggerInterface
    {
        return $this->logManager->channel('device_control');
    }
}
