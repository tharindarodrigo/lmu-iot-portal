<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Widgets\StatusSummary;

use App\Domain\DeviceSchema\Models\DerivedParameterDefinition;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\IoTDashboard\Application\DashboardHistoryRange;
use App\Domain\IoTDashboard\Contracts\WidgetConfig;
use App\Domain\IoTDashboard\Contracts\WidgetSnapshotResolver;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\IoTDashboard\Widgets\Concerns\InterpretsThresholdStatusSnapshot;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class StatusSummarySnapshotResolver implements WidgetSnapshotResolver
{
    use InterpretsThresholdStatusSnapshot;

    public function __construct(
        private readonly LatestParameterMetricSourceResolver $latestParameterResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(
        IoTDashboardWidget $widget,
        WidgetConfig $config,
        ?DashboardHistoryRange $historyRange = null,
    ): array {
        if (! $config instanceof StatusSummaryConfig) {
            throw new InvalidArgumentException('Status summary widgets require StatusSummaryConfig.');
        }

        $series = [];
        $latestLog = $this->fetchLatestTelemetryLog(
            schemaVersionTopicId: (int) $widget->schema_version_topic_id,
            deviceId: is_numeric($widget->device_id) ? (int) $widget->device_id : null,
            lookbackMinutes: $config->lookbackMinutes(),
        );

        foreach ($config->tiles() as $tile) {
            $resolvedTile = $this->latestParameterResolver->resolve($widget, $tile, $latestLog);
            $resolvedColor = $this->resolveTileColor($tile, $resolvedTile['value']);
            $resolvedUnit = $this->resolveTileUnit($widget, $tile);

            $series[] = [
                'key' => $tile['key'],
                'label' => $tile['label'],
                'unit' => $resolvedUnit,
                'color' => $resolvedColor,
                'points' => $resolvedTile['value'] === null || ! $resolvedTile['timestamp'] instanceof CarbonImmutable
                    ? []
                    : [[
                        'timestamp' => $resolvedTile['timestamp']->toIso8601String(),
                        'value' => $resolvedTile['value'],
                    ]],
            ];
        }

        $device = $widget->device;

        return [
            'widget_id' => (int) $widget->id,
            'generated_at' => now()->toIso8601String(),
            'device_connection_state' => $device?->effectiveConnectionState(),
            'device_last_seen_at' => $device?->lastSeenAt()?->toIso8601String(),
            'series' => $series,
        ];
    }

    private function fetchLatestTelemetryLog(
        int $schemaVersionTopicId,
        ?int $deviceId,
        int $lookbackMinutes,
    ): ?DeviceTelemetryLog {
        if ($deviceId === null || $schemaVersionTopicId < 1) {
            return null;
        }

        return DeviceTelemetryLog::query()
            ->where('schema_version_topic_id', $schemaVersionTopicId)
            ->where('device_id', $deviceId)
            ->where('recorded_at', '>=', now()->subMinutes($lookbackMinutes))
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first(['id', 'recorded_at', 'transformed_values']);
    }

    /**
     * @param  array{
     *     threshold_ranges: array<int, array{from: int|float|null, to: int|float|null, color: string}>,
     *     base_color: string
     * }  $tile
     */
    private function resolveTileColor(array $tile, int|float|null $value): string
    {
        if ($value === null) {
            return $tile['base_color'];
        }

        foreach ($tile['threshold_ranges'] as $range) {
            $from = $range['from'];
            $to = $range['to'];

            if ($from !== null && $value < $from) {
                continue;
            }

            if ($to !== null && $value > $to) {
                continue;
            }

            return $range['color'];
        }

        return $tile['base_color'];
    }

    /**
     * @param  array{unit: string|null, source: array<string, mixed>}  $tile
     */
    private function resolveTileUnit(IoTDashboardWidget $widget, array $tile): ?string
    {
        if (is_string($tile['unit'] ?? null) && trim($tile['unit']) !== '') {
            return $this->resolveUnitSymbol(trim($tile['unit']));
        }

        $parameterKey = is_string($tile['source']['parameter_key'] ?? null)
            ? trim($tile['source']['parameter_key'])
            : '';

        if ($parameterKey === '') {
            return null;
        }

        $parameterUnit = ParameterDefinition::query()
            ->where('schema_version_topic_id', (int) $widget->schema_version_topic_id)
            ->where('key', $parameterKey)
            ->value('unit');

        if (is_string($parameterUnit) && trim($parameterUnit) !== '') {
            return $this->resolveUnitSymbol(trim($parameterUnit));
        }

        $topic = SchemaVersionTopic::query()
            ->whereKey((int) $widget->schema_version_topic_id)
            ->first(['id', 'device_schema_version_id']);

        if (! $topic instanceof SchemaVersionTopic) {
            return null;
        }

        $derivedUnit = DerivedParameterDefinition::query()
            ->where('device_schema_version_id', $topic->device_schema_version_id)
            ->where('key', $parameterKey)
            ->value('unit');

        return is_string($derivedUnit) && trim($derivedUnit) !== ''
            ? $this->resolveUnitSymbol(trim($derivedUnit))
            : null;
    }
}
