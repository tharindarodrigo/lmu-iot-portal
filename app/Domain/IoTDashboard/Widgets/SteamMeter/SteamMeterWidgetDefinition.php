<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Widgets\SteamMeter;

use App\Domain\IoTDashboard\Application\DashboardHistoryRange;
use App\Domain\IoTDashboard\Contracts\WidgetConfig;
use App\Domain\IoTDashboard\Contracts\WidgetDefinition;
use App\Domain\IoTDashboard\Enums\WidgetType;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;

class SteamMeterWidgetDefinition implements WidgetDefinition
{
    public function __construct(
        private readonly SteamMeterSnapshotResolver $snapshotResolver,
    ) {}

    public function type(): WidgetType
    {
        return WidgetType::SteamMeter;
    }

    public function makeConfig(array $config): WidgetConfig
    {
        return SteamMeterConfig::fromArray($config);
    }

    public function resolveSnapshot(IoTDashboardWidget $widget, ?DashboardHistoryRange $historyRange = null): array
    {
        return $this->snapshotResolver->resolve($widget, $this->makeConfig($widget->configArray()), $historyRange);
    }

    public function bootstrapPayload(IoTDashboardWidget $widget): array
    {
        $config = $this->makeConfig($widget->configArray());

        return [
            'use_websocket' => false,
            'use_polling' => $config->usePolling(),
            'polling_interval_seconds' => $config->pollingIntervalSeconds(),
            'lookback_minutes' => $config->lookbackMinutes(),
            'max_points' => $config->maxPoints(),
            'shifts' => $config instanceof SteamMeterConfig ? $config->shifts() : [],
        ];
    }
}
