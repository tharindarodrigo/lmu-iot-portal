<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Widgets\ThresholdStatusGrid;

use App\Domain\Automation\Models\AutomationThresholdPolicy;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Enums\MetricUnit;
use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\IoTDashboard\Application\DashboardHistoryRange;
use App\Domain\IoTDashboard\Contracts\WidgetConfig;
use App\Domain\IoTDashboard\Contracts\WidgetSnapshotResolver;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use App\Filament\Admin\Resources\AutomationThresholdPolicies\AutomationThresholdPolicyResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use InvalidArgumentException;

class ThresholdStatusGridSnapshotResolver implements WidgetSnapshotResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(
        IoTDashboardWidget $widget,
        WidgetConfig $config,
        ?DashboardHistoryRange $historyRange = null,
    ): array {
        if (! $config instanceof ThresholdStatusGridConfig) {
            throw new InvalidArgumentException('Threshold status grid widgets require ThresholdStatusGridConfig.');
        }

        $organizationId = $widget->dashboard()->value('organization_id');

        if (! is_numeric($organizationId)) {
            return [
                'widget_id' => (int) $widget->id,
                'generated_at' => now()->toIso8601String(),
                'cards' => [],
            ];
        }

        if ($config->scope() === 'device_cards' && $config->deviceCards() !== []) {
            return [
                'widget_id' => (int) $widget->id,
                'generated_at' => now()->toIso8601String(),
                'cards' => $this->resolveConfiguredCards((int) $organizationId, $config),
            ];
        }

        $policies = $this->resolvePolicies((int) $organizationId, $config);
        $latestLogs = $this->fetchLatestLogsForPairs(
            $this->policyPairs($policies),
            $config->lookbackMinutes(),
        );

        return [
            'widget_id' => (int) $widget->id,
            'generated_at' => now()->toIso8601String(),
            'cards' => $policies
                ->map(fn (AutomationThresholdPolicy $policy): array => $this->resolveCard($policy, $latestLogs, $config->displayMode()))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, AutomationThresholdPolicy>
     */
    private function resolvePolicies(int $organizationId, ThresholdStatusGridConfig $config): Collection
    {
        return AutomationThresholdPolicy::query()
            ->with([
                'device',
                'parameterDefinition.topic',
                'notificationProfile',
            ])
            ->where('organization_id', $organizationId)
            ->when(
                $config->scope() === 'selected' && $config->policyIds() !== [],
                fn ($query) => $query->whereIn('id', $config->policyIds()),
                fn ($query) => $query->where('is_active', true),
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, AutomationThresholdPolicy>  $policies
     * @return Collection<int, array{device_id: int, topic_id: int}>
     */
    private function policyPairs(Collection $policies): Collection
    {
        return $policies
            ->map(function (AutomationThresholdPolicy $policy): ?array {
                $parameter = $policy->parameterDefinition;

                if (! $parameter instanceof ParameterDefinition) {
                    return null;
                }

                return [
                    'device_id' => (int) $policy->device_id,
                    'topic_id' => (int) $parameter->schema_version_topic_id,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, array{device_id: int, topic_id: int}>  $pairs
     * @return Collection<string, DeviceTelemetryLog>
     */
    private function fetchLatestLogsForPairs(Collection $pairs, int $lookbackMinutes): Collection
    {
        if ($pairs->isEmpty()) {
            return collect();
        }

        $deviceIds = $pairs->pluck('device_id')->unique()->all();
        $topicIds = $pairs->pluck('topic_id')->unique()->all();

        return DeviceTelemetryLog::query()
            ->whereIn('device_id', $deviceIds)
            ->whereIn('schema_version_topic_id', $topicIds)
            ->where('recorded_at', '>=', now()->subMinutes($lookbackMinutes))
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->get(['id', 'device_id', 'schema_version_topic_id', 'recorded_at', 'transformed_values'])
            ->groupBy(fn (DeviceTelemetryLog $log): string => $this->pairKey((int) $log->device_id, (int) $log->schema_version_topic_id))
            ->map(function (Collection $logs): DeviceTelemetryLog {
                $latestLog = $logs->first();

                if (! $latestLog instanceof DeviceTelemetryLog) {
                    throw new InvalidArgumentException('Threshold status grid expected grouped telemetry logs.');
                }

                return $latestLog;
            });
    }

    /**
     * @param  Collection<string, DeviceTelemetryLog>  $latestLogs
     * @return array<string, mixed>
     */
    private function resolveCard(
        AutomationThresholdPolicy $policy,
        Collection $latestLogs,
        string $displayMode,
    ): array {
        $parameter = $policy->parameterDefinition;
        $device = $policy->device;
        $topicId = $parameter instanceof ParameterDefinition
            ? (int) $parameter->schema_version_topic_id
            : null;
        $deviceId = (int) $policy->device_id;
        $latestLog = $topicId !== null
            ? $latestLogs->get($this->pairKey($deviceId, $topicId))
            : null;
        $payload = is_array($latestLog?->transformed_values) ? $latestLog->transformed_values : [];
        $value = $parameter?->extractValue($payload);
        $numericValue = is_numeric($value) ? (float) $value : null;
        $connectionState = $device instanceof Device
            ? strtolower($device->effectiveConnectionState())
            : 'unknown';
        $minimumValue = is_numeric($policy->minimum_value) ? (float) $policy->minimum_value : null;
        $maximumValue = is_numeric($policy->maximum_value) ? (float) $policy->maximum_value : null;
        $status = $this->resolveStatus(
            minimumValue: $minimumValue,
            maximumValue: $maximumValue,
            connectionState: $connectionState,
            value: $numericValue,
        );
        $unit = $this->resolveUnitSymbol($parameter?->unit);

        return [
            'policy_id' => (int) $policy->id,
            'device_name' => $device instanceof Device ? $device->name : $policy->name,
            'connection_state' => strtoupper($connectionState),
            'last_telemetry_at' => $latestLog?->recorded_at?->toIso8601String(),
            'range_label' => $this->resolveRangeLabel(
                minimumValue: $minimumValue,
                maximumValue: $maximumValue,
                unit: $parameter?->unit,
                displayMode: $displayMode,
            ),
            'status' => $status,
            'status_label' => strtoupper(str_replace('_', ' ', $status)),
            'current_value' => $numericValue,
            'current_value_display' => $numericValue === null
                ? '—'
                : $this->formatValue($numericValue, $unit),
            'unit' => $unit,
            'is_active' => (bool) $policy->is_active,
            'edit_url' => AutomationThresholdPolicyResource::getUrl('edit', ['record' => $policy]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveConfiguredCards(int $organizationId, ThresholdStatusGridConfig $config): array
    {
        $resolvedCards = $this->resolveConfiguredCardScopes($organizationId, $config);

        if ($resolvedCards->isEmpty()) {
            return [];
        }

        $latestLogs = $this->fetchLatestLogsForPairs(
            $resolvedCards
                ->map(fn (array $scope): array => [
                    'device_id' => (int) $scope['device']->id,
                    'topic_id' => (int) $scope['topic']->id,
                ]),
            $config->lookbackMinutes(),
        );

        $matchingPolicies = AutomationThresholdPolicy::query()
            ->with('parameterDefinition')
            ->where('organization_id', $organizationId)
            ->whereIn('device_id', $resolvedCards->pluck('device.id')->unique()->all())
            ->get()
            ->filter(fn (AutomationThresholdPolicy $policy): bool => $policy->parameterDefinition instanceof ParameterDefinition)
            ->keyBy(fn (AutomationThresholdPolicy $policy): string => $this->deviceParameterKey(
                (int) $policy->device_id,
                (string) $policy->parameterDefinition?->key,
            ));

        return array_values(
            $resolvedCards
                ->map(fn (array $scope): array => $this->resolveConfiguredCard(
                    $scope,
                    $latestLogs,
                    $matchingPolicies,
                    $config->displayMode(),
                ))
                ->all(),
        );
    }

    /**
     * @return Collection<int, array{
     *     card: array{
     *         device_id: int,
     *         label: string|null,
     *         parameter_key: string,
     *         minimum_value: float|null,
     *         maximum_value: float|null
     *     },
     *     device: Device,
     *     topic: SchemaVersionTopic,
     *     parameter: ParameterDefinition
     * }>
     */
    private function resolveConfiguredCardScopes(int $organizationId, ThresholdStatusGridConfig $config): Collection
    {
        $deviceIds = collect($config->deviceCards())
            ->pluck('device_id')
            ->unique()
            ->values()
            ->all();

        $devices = Device::query()
            ->with([
                'schemaVersion.topics' => fn ($query) => $query
                    ->where('direction', TopicDirection::Publish->value)
                    ->with([
                        'parameters' => fn ($query) => $query
                            ->where('is_active', true)
                            ->orderBy('sequence')
                            ->orderBy('id'),
                    ]),
            ])
            ->where('organization_id', $organizationId)
            ->whereIn('id', $deviceIds)
            ->get()
            ->keyBy('id');

        return collect($config->deviceCards())
            ->map(function (array $card) use ($devices): ?array {
                $device = $devices->get($card['device_id']);

                if (! $device instanceof Device) {
                    return null;
                }

                $parameter = $this->resolveConfiguredParameter($device, $card['parameter_key']);

                if (! $parameter instanceof ParameterDefinition || ! $parameter->topic instanceof SchemaVersionTopic) {
                    return null;
                }

                return [
                    'card' => $card,
                    'device' => $device,
                    'topic' => $parameter->topic,
                    'parameter' => $parameter,
                ];
            })
            ->filter()
            ->values();
    }

    private function resolveConfiguredParameter(Device $device, string $parameterKey): ?ParameterDefinition
    {
        return $device->schemaVersion?->topics
            ?->flatMap(fn (SchemaVersionTopic $topic) => $topic->parameters)
            ->first(fn (ParameterDefinition $parameter): bool => $parameter->key === $parameterKey);
    }

    /**
     * @param  array{
     *     card: array{
     *         device_id: int,
     *         label: string|null,
     *         parameter_key: string,
     *         minimum_value: float|null,
     *         maximum_value: float|null
     *     },
     *     device: Device,
     *     topic: SchemaVersionTopic,
     *     parameter: ParameterDefinition
     * }  $scope
     * @param  Collection<string, DeviceTelemetryLog>  $latestLogs
     * @param  Collection<string, AutomationThresholdPolicy>  $matchingPolicies
     * @return array<string, mixed>
     */
    private function resolveConfiguredCard(
        array $scope,
        Collection $latestLogs,
        Collection $matchingPolicies,
        string $displayMode,
    ): array {
        $device = $scope['device'];
        $topic = $scope['topic'];
        $parameter = $scope['parameter'];
        $card = $scope['card'];
        $latestLog = $latestLogs->get($this->pairKey((int) $device->id, (int) $topic->id));
        $payload = is_array($latestLog?->transformed_values) ? $latestLog->transformed_values : [];
        $value = $parameter->extractValue($payload);
        $numericValue = is_numeric($value) ? (float) $value : null;
        $connectionState = strtolower($device->effectiveConnectionState());
        $policy = $matchingPolicies->get($this->deviceParameterKey((int) $device->id, $parameter->key));
        $minimumValue = $card['minimum_value']
            ?? (is_numeric($policy?->minimum_value) ? (float) $policy->minimum_value : null);
        $maximumValue = $card['maximum_value']
            ?? (is_numeric($policy?->maximum_value) ? (float) $policy->maximum_value : null);
        $status = $this->resolveStatus(
            minimumValue: $minimumValue,
            maximumValue: $maximumValue,
            connectionState: $connectionState,
            value: $numericValue,
        );
        $unit = $this->resolveUnitSymbol($parameter->unit);

        return [
            'policy_id' => $policy?->id === null ? null : (int) $policy->id,
            'device_name' => $card['label'] ?? $device->name,
            'connection_state' => strtoupper($connectionState),
            'last_telemetry_at' => $latestLog?->recorded_at?->toIso8601String(),
            'range_label' => $this->resolveRangeLabel(
                minimumValue: $minimumValue,
                maximumValue: $maximumValue,
                unit: $parameter->unit,
                displayMode: $displayMode,
            ),
            'status' => $status,
            'status_label' => strtoupper(str_replace('_', ' ', $status)),
            'current_value' => $numericValue,
            'current_value_display' => $numericValue === null
                ? '—'
                : $this->formatValue($numericValue, $unit),
            'unit' => $unit,
            'is_active' => $policy instanceof AutomationThresholdPolicy ? (bool) $policy->is_active : true,
            'edit_url' => $policy instanceof AutomationThresholdPolicy
                ? AutomationThresholdPolicyResource::getUrl('edit', ['record' => $policy])
                : null,
        ];
    }

    private function resolveStatus(
        ?float $minimumValue,
        ?float $maximumValue,
        string $connectionState,
        ?float $value,
    ): string {
        if ($connectionState === 'offline') {
            return 'offline';
        }

        if ($value === null) {
            return 'no_data';
        }

        if ($minimumValue !== null && $value < $minimumValue) {
            return 'alert';
        }

        if ($maximumValue !== null && $value > $maximumValue) {
            return 'alert';
        }

        return 'normal';
    }

    private function resolveRangeLabel(
        ?float $minimumValue,
        ?float $maximumValue,
        ?string $unit,
        string $displayMode,
    ): string {
        if ($displayMode === 'sri_lankan_temperature') {
            return $this->resolveSriLankanRangeLabel($minimumValue, $maximumValue, $unit);
        }

        if ($minimumValue !== null && $maximumValue !== null) {
            return sprintf(
                '%s to %s',
                $this->formatValue($minimumValue, $this->resolveUnitSymbol($unit)),
                $this->formatValue($maximumValue, $this->resolveUnitSymbol($unit)),
            );
        }

        if ($minimumValue !== null) {
            return 'At least '.$this->formatValue($minimumValue, $this->resolveUnitSymbol($unit));
        }

        if ($maximumValue !== null) {
            return 'At most '.$this->formatValue($maximumValue, $this->resolveUnitSymbol($unit));
        }

        return 'No thresholds configured';
    }

    private function resolveSriLankanRangeLabel(?float $minimumValue, ?float $maximumValue, ?string $unit): string
    {
        if ($minimumValue !== null && $maximumValue !== null) {
            return sprintf(
                '%s > Temp > %s',
                $this->formatValue($minimumValue, $this->resolveUnitSymbol($unit)),
                $this->formatValue($maximumValue, $this->resolveUnitSymbol($unit)),
            );
        }

        if ($minimumValue !== null) {
            return 'Temp > '.$this->formatValue($minimumValue, $this->resolveUnitSymbol($unit));
        }

        if ($maximumValue !== null) {
            return 'Temp < '.$this->formatValue($maximumValue, $this->resolveUnitSymbol($unit));
        }

        return 'No thresholds configured';
    }

    private function pairKey(int $deviceId, int $topicId): string
    {
        return "{$deviceId}:{$topicId}";
    }

    private function deviceParameterKey(int $deviceId, string $parameterKey): string
    {
        return "{$deviceId}:{$parameterKey}";
    }

    private function formatValue(float $value, ?string $unit = null): string
    {
        $formattedNumber = Number::format($value, maxPrecision: 2);
        $formattedValue = is_string($formattedNumber)
            ? (str_contains($formattedNumber, '.')
                ? rtrim(rtrim($formattedNumber, '0'), '.')
                : $formattedNumber)
            : (string) $value;

        return is_string($unit) && $unit !== ''
            ? $formattedValue.$unit
            : $formattedValue;
    }

    private function resolveUnitSymbol(?string $unit): ?string
    {
        return match ($unit) {
            MetricUnit::Celsius->value => '°C',
            MetricUnit::Percent->value => '%',
            MetricUnit::Volts->value => 'V',
            MetricUnit::Amperes->value => 'A',
            MetricUnit::KilowattHours->value => 'kWh',
            MetricUnit::Watts->value => 'W',
            MetricUnit::Seconds->value => 's',
            MetricUnit::DecibelMilliwatts->value => 'dBm',
            MetricUnit::RevolutionsPerMinute->value => 'RPM',
            MetricUnit::Litres->value => 'L',
            MetricUnit::CubicMeters->value => 'm³',
            MetricUnit::LitersPerMinute->value => 'L/min',
            default => $unit,
        };
    }
}
