<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Widgets\ThresholdStatusGrid;

use App\Domain\IoTDashboard\Application\DashboardHistoryRange;
use App\Domain\IoTDashboard\Contracts\WidgetDefinition;
use App\Domain\IoTDashboard\Enums\WidgetType;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;

class ThresholdStatusGridWidgetDefinition implements WidgetDefinition
{
    public function __construct(
        private readonly ThresholdStatusGridSnapshotResolver $snapshotResolver,
    ) {}

    public function type(): WidgetType
    {
        return WidgetType::ThresholdStatusGrid;
    }

    public function makeConfig(array $config): ThresholdStatusGridConfig
    {
        return ThresholdStatusGridConfig::fromArray($config);
    }

    public function resolveSnapshot(IoTDashboardWidget $widget, ?DashboardHistoryRange $historyRange = null): array
    {
        return $this->snapshotResolver->resolve($widget, $this->makeConfig($widget->configArray()), $historyRange);
    }

    public function bootstrapPayload(IoTDashboardWidget $widget): array
    {
        $config = $this->makeConfig($widget->configArray());

        return [
            'cards' => [],
            'scope' => $config->scope(),
            'policy_ids' => $config->policyIds(),
            'display_mode' => $config->displayMode(),
            'configured_device_count' => count($config->deviceCards()),
            'use_websocket' => false,
            'use_polling' => $config->usePolling(),
            'polling_interval_seconds' => $config->pollingIntervalSeconds(),
            'lookback_minutes' => $config->lookbackMinutes(),
            'max_points' => $config->maxPoints(),
        ];
    }
}
