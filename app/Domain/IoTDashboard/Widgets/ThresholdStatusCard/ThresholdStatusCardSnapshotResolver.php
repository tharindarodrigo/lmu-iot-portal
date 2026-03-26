<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Widgets\ThresholdStatusCard;

use App\Domain\Automation\Models\AutomationThresholdPolicy;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Enums\MetricUnit;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Services\JsonLogicEvaluator;
use App\Domain\IoTDashboard\Application\DashboardHistoryRange;
use App\Domain\IoTDashboard\Contracts\WidgetConfig;
use App\Domain\IoTDashboard\Contracts\WidgetSnapshotResolver;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use App\Filament\Admin\Resources\AutomationThresholdPolicies\AutomationThresholdPolicyResource;
use Illuminate\Support\Number;
use InvalidArgumentException;

class ThresholdStatusCardSnapshotResolver implements WidgetSnapshotResolver
{
    public function __construct(
        private readonly JsonLogicEvaluator $jsonLogicEvaluator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(
        IoTDashboardWidget $widget,
        WidgetConfig $config,
        ?DashboardHistoryRange $historyRange = null,
    ): array {
        if (! $config instanceof ThresholdStatusCardConfig) {
            throw new InvalidArgumentException('Threshold status card widgets require ThresholdStatusCardConfig.');
        }

        $organizationId = $widget->dashboard()->value('organization_id');

        if (! is_numeric($organizationId) || $config->policyId() < 1) {
            return [
                'widget_id' => (int) $widget->id,
                'generated_at' => now()->toIso8601String(),
                'card' => null,
            ];
        }

        $policy = AutomationThresholdPolicy::query()
            ->with(['device', 'parameterDefinition.topic'])
            ->where('organization_id', (int) $organizationId)
            ->find($config->policyId());

        if (! $policy instanceof AutomationThresholdPolicy) {
            return [
                'widget_id' => (int) $widget->id,
                'generated_at' => now()->toIso8601String(),
                'card' => null,
            ];
        }

        $parameter = $policy->parameterDefinition;
        $device = $policy->device;
        $topicId = $parameter instanceof ParameterDefinition ? (int) $parameter->schema_version_topic_id : null;

        $latestLog = $topicId === null
            ? null
            : DeviceTelemetryLog::query()
                ->where('device_id', (int) $policy->device_id)
                ->where('schema_version_topic_id', $topicId)
                ->where('recorded_at', '>=', now()->subMinutes($config->lookbackMinutes()))
                ->latest('recorded_at')
                ->latest('id')
                ->first();

        $payload = is_array($latestLog?->transformed_values) ? $latestLog->transformed_values : [];
        $value = $parameter?->extractValue($payload);
        $numericValue = is_numeric($value) ? (float) $value : null;
        $connectionState = $device instanceof Device
            ? strtolower($device->effectiveConnectionState())
            : 'unknown';
        $status = $this->resolveStatus($policy, $connectionState, $numericValue);
        $unit = $this->resolveUnitSymbol($parameter?->unit);
        $alertTriggeredAt = $status === 'alert'
            ? $latestLog?->recorded_at?->toIso8601String()
            : null;

        return [
            'widget_id' => (int) $widget->id,
            'generated_at' => now()->toIso8601String(),
            'device_connection_state' => $device?->effectiveConnectionState(),
            'device_last_seen_at' => $device?->lastSeenAt()?->toIso8601String(),
            'card' => [
                'policy_id' => (int) $policy->id,
                'device_name' => $device instanceof Device ? $device->name : $policy->name,
                'connection_state' => strtoupper($connectionState),
                'last_telemetry_at' => $latestLog?->recorded_at?->toIso8601String(),
                'rule_label' => $policy->conditionLabel($parameter?->unit),
                'status' => $status,
                'status_label' => strtoupper(str_replace('_', ' ', $status)),
                'alert_triggered_at' => $alertTriggeredAt,
                'current_value' => $numericValue,
                'current_value_display' => $numericValue === null ? '—' : $this->formatValue($numericValue, $unit),
                'edit_url' => AutomationThresholdPolicyResource::getUrl('edit', ['record' => $policy]),
                'is_active' => (bool) $policy->is_active,
            ],
        ];
    }

    private function resolveStatus(AutomationThresholdPolicy $policy, string $connectionState, ?float $value): string
    {
        if ($policy->is_active !== true) {
            return 'inactive';
        }

        if ($connectionState === 'offline') {
            return 'offline';
        }

        if ($value === null) {
            return 'no_data';
        }

        $isBreached = $this->jsonLogicEvaluator->evaluate($policy->resolvedConditionJsonLogic(), [
            'trigger' => ['value' => $value],
            'query' => ['value' => $value],
        ]);

        return $isBreached ? 'alert' : 'normal';
    }

    private function resolveUnitSymbol(?string $unit): ?string
    {
        return match ($unit) {
            MetricUnit::Celsius->value => '°C',
            MetricUnit::Percent->value => '%',
            MetricUnit::Volts->value => 'V',
            MetricUnit::Watts->value => 'W',
            MetricUnit::Seconds->value => 's',
            default => is_string($unit) && trim($unit) !== '' ? trim($unit) : null,
        };
    }

    private function formatValue(float $value, ?string $unit = null): string
    {
        $formattedNumber = Number::format($value, maxPrecision: 3);
        $formattedValue = is_string($formattedNumber)
            ? (str_contains($formattedNumber, '.')
                ? rtrim(rtrim($formattedNumber, '0'), '.')
                : $formattedNumber)
            : (string) $value;

        return $unit === null ? $formattedValue : $formattedValue.$unit;
    }
}
