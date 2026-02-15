<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Services;

use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class GaugeWidgetDataResolver
{
    /**
     * @return array{
     *     widget_id: int,
     *     topic_id: int,
     *     generated_at: string,
     *     series: array<int, array{
     *         key: string,
     *         label: string,
     *         color: string,
     *         points: array<int, array{timestamp: string, value: int|float}>
     *     }>
     * }
     */
    public function resolve(
        IoTDashboardWidget $widget,
        int $lookbackMinutes,
    ): array {
        $deviceId = is_numeric($widget->device_id) ? (int) $widget->device_id : null;
        $seriesConfiguration = $widget->resolvedSeriesConfig();
        $latestLog = $deviceId === null
            ? null
            : $this->fetchLatestTelemetryLog(
                schemaVersionTopicId: (int) $widget->schema_version_topic_id,
                deviceId: $deviceId,
                lookbackMinutes: $lookbackMinutes,
            );

        $series = [];

        foreach ($seriesConfiguration as $config) {
            $latestPoint = $this->resolveLatestPoint($latestLog, $config['key']);

            $series[] = [
                'key' => $config['key'],
                'label' => $config['label'],
                'color' => $config['color'],
                'points' => $latestPoint === null ? [] : [$latestPoint],
            ];
        }

        return [
            'widget_id' => (int) $widget->id,
            'topic_id' => (int) $widget->schema_version_topic_id,
            'device_id' => $deviceId,
            'generated_at' => now()->toIso8601String(),
            'series' => $series,
        ];
    }

    private function fetchLatestTelemetryLog(
        int $schemaVersionTopicId,
        ?int $deviceId,
        int $lookbackMinutes,
    ): ?DeviceTelemetryLog {
        return DeviceTelemetryLog::query()
            ->where('schema_version_topic_id', $schemaVersionTopicId)
            ->when(
                is_numeric($deviceId) && $deviceId > 0,
                fn (Builder $query): Builder => $query->where('device_id', $deviceId),
            )
            ->where('recorded_at', '>=', now()->subMinutes($lookbackMinutes))
            ->orderByDesc('recorded_at')
            ->first(['id', 'recorded_at', 'transformed_values']);
    }

    /**
     * @return array{timestamp: string, value: int|float}|null
     */
    private function resolveLatestPoint(?DeviceTelemetryLog $log, string $parameterKey): ?array
    {
        if (! $log instanceof DeviceTelemetryLog) {
            return null;
        }

        $value = $this->extractNumericValue($log->transformed_values, $parameterKey);
        $recordedAt = $this->normalizeRecordedAt($log->recorded_at);
        $timestamp = $recordedAt?->toIso8601String();

        if ($value === null || ! is_string($timestamp)) {
            return null;
        }

        return [
            'timestamp' => $timestamp,
            'value' => $value,
        ];
    }

    private function extractNumericValue(mixed $values, string $parameterKey): int|float|null
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

    private function normalizeRecordedAt(mixed $recordedAt): ?Carbon
    {
        if ($recordedAt instanceof Carbon) {
            return $recordedAt;
        }

        if (is_string($recordedAt) && trim($recordedAt) !== '') {
            return Carbon::parse($recordedAt);
        }

        return null;
    }
}
