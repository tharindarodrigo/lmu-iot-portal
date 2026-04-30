<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Widgets\LineChart;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\VirtualDeviceLink;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\IoTDashboard\Application\DashboardHistoryRange;
use App\Domain\IoTDashboard\Contracts\WidgetConfig;
use App\Domain\IoTDashboard\Contracts\WidgetSnapshotResolver;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class LineChartSnapshotResolver implements WidgetSnapshotResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(
        IoTDashboardWidget $widget,
        WidgetConfig $config,
        ?DashboardHistoryRange $historyRange = null,
    ): array {
        if (! $config instanceof LineChartConfig) {
            throw new InvalidArgumentException('Line chart widgets require LineChartConfig.');
        }

        $series = [];
        $logsBySourceKey = [];

        foreach ($config->series() as $seriesConfiguration) {
            $points = [];
            $key = $seriesConfiguration['key'];
            $source = array_key_exists('source', $seriesConfiguration) ? $seriesConfiguration['source'] : [];
            $sourceBinding = $this->resolveSeriesSourceBinding($widget, $source);
            $sourceKey = $this->sourceCacheKey($sourceBinding);
            $logs = $logsBySourceKey[$sourceKey] ??= $sourceBinding === null
                ? collect()
                : $this->fetchTelemetryLogs(
                    schemaVersionTopicId: $sourceBinding['schema_version_topic_id'],
                    deviceId: $sourceBinding['device_id'],
                    lookbackMinutes: $config->lookbackMinutes(),
                    maxPoints: $config->maxPoints(),
                    historyRange: $historyRange,
                );

            foreach ($logs as $log) {
                $value = $this->extractNumericValue($log->transformed_values, $key);

                if ($value === null) {
                    continue;
                }

                $timestamp = $log->recorded_at?->toIso8601String();

                if (! is_string($timestamp)) {
                    continue;
                }

                $points[] = [
                    'timestamp' => $timestamp,
                    'value' => $value,
                ];
            }

            $resolvedSeries = [
                'key' => $seriesConfiguration['key'],
                'label' => $seriesConfiguration['label'],
                'color' => $seriesConfiguration['color'],
                'points' => $points,
            ];

            if (array_key_exists('source', $seriesConfiguration)) {
                $resolvedSeries['source'] = $seriesConfiguration['source'];
            }

            $series[] = $resolvedSeries;
        }

        return [
            'widget_id' => (int) $widget->id,
            'generated_at' => now()->toIso8601String(),
            'series' => $series,
        ];
    }

    /**
     * @param  array<string, mixed>|mixed  $source
     * @return array{device_id: int, schema_version_topic_id: int}|null
     */
    private function resolveSeriesSourceBinding(IoTDashboardWidget $widget, mixed $source): ?array
    {
        if (is_array($source) && ($source['type'] ?? null) === 'virtual_device_link') {
            $purpose = is_string($source['purpose'] ?? null) ? trim((string) $source['purpose']) : '';

            if ($purpose === '') {
                return null;
            }

            $sourceDevice = $this->resolveVirtualSourceDevice($widget, $purpose);

            return $sourceDevice instanceof Device
                ? $this->bindingForDevice($sourceDevice)
                : null;
        }

        $deviceId = (int) $widget->device_id;
        $schemaVersionTopicId = (int) $widget->schema_version_topic_id;

        if ($deviceId < 1 || $schemaVersionTopicId < 1) {
            return null;
        }

        return [
            'device_id' => $deviceId,
            'schema_version_topic_id' => $schemaVersionTopicId,
        ];
    }

    private function resolveVirtualSourceDevice(IoTDashboardWidget $widget, string $purpose): ?Device
    {
        $virtualDeviceId = (int) $widget->device_id;

        if ($virtualDeviceId < 1) {
            return null;
        }

        $link = VirtualDeviceLink::query()
            ->with('sourceDevice.schemaVersion.topics')
            ->where('virtual_device_id', $virtualDeviceId)
            ->where('purpose', $purpose)
            ->orderBy('sequence')
            ->first();

        return $link?->sourceDevice;
    }

    /**
     * @return array{device_id: int, schema_version_topic_id: int}|null
     */
    private function bindingForDevice(Device $device): ?array
    {
        $device->loadMissing('schemaVersion.topics');

        $topic = $device->schemaVersion?->topics
            ?->first(fn (SchemaVersionTopic $topic): bool => $topic->key === 'telemetry')
            ?? $device->schemaVersion?->topics?->first(fn (SchemaVersionTopic $topic): bool => $topic->isPublish());

        if (! $topic instanceof SchemaVersionTopic) {
            return null;
        }

        return [
            'device_id' => (int) $device->id,
            'schema_version_topic_id' => (int) $topic->id,
        ];
    }

    /**
     * @param  array{device_id: int, schema_version_topic_id: int}|null  $sourceBinding
     */
    private function sourceCacheKey(?array $sourceBinding): string
    {
        if ($sourceBinding === null) {
            return 'missing';
        }

        return $sourceBinding['device_id'].':'.$sourceBinding['schema_version_topic_id'];
    }

    /**
     * @return Collection<int, DeviceTelemetryLog>
     */
    private function fetchTelemetryLogs(
        int $schemaVersionTopicId,
        int $deviceId,
        int $lookbackMinutes,
        int $maxPoints,
        ?DashboardHistoryRange $historyRange,
    ): Collection {
        $query = DeviceTelemetryLog::query()
            ->where('schema_version_topic_id', $schemaVersionTopicId)
            ->where('device_id', $deviceId);

        if ($historyRange instanceof DashboardHistoryRange) {
            $query
                ->where('recorded_at', '>=', $historyRange->fromAt())
                ->where('recorded_at', '<=', $historyRange->untilAt());
        } else {
            $query->where('recorded_at', '>=', now()->subMinutes($lookbackMinutes));
        }

        return $query
            ->orderByDesc('recorded_at')
            ->limit($maxPoints)
            ->get(['id', 'recorded_at', 'transformed_values'])
            ->sortBy('recorded_at')
            ->values();
    }

    /**
     * @param  array<string, mixed>|null  $values
     */
    private function extractNumericValue(?array $values, string $parameterKey): int|float|null
    {
        if (! is_array($values)) {
            return null;
        }

        $value = data_get($values, $parameterKey);

        if (is_numeric($value)) {
            return $value + 0;
        }

        return null;
    }
}
