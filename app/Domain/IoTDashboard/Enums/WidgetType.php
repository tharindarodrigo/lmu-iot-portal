<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Enums;

use Filament\Support\Contracts\HasLabel;

enum WidgetType: string implements HasLabel
{
    case LineChart = 'line_chart';
    case BarChart = 'bar_chart';
    case GaugeChart = 'gauge_chart';
    case StatusSummary = 'status_summary';
    case StateCard = 'state_card';
    case StateTimeline = 'state_timeline';
    case ThresholdStatusCard = 'threshold_status_card';
    case ThresholdStatusGrid = 'threshold_status_grid';

    public function getLabel(): string
    {
        return match ($this) {
            self::LineChart => 'Line Chart',
            self::BarChart => 'Bar Chart',
            self::GaugeChart => 'Gauge Chart',
            self::StatusSummary => 'Status Summary',
            self::StateCard => 'State Card',
            self::StateTimeline => 'State Timeline',
            self::ThresholdStatusCard => 'Threshold Status Card',
            self::ThresholdStatusGrid => 'Threshold Status Grid',
        };
    }

    public function label(): string
    {
        return $this->getLabel();
    }
}
