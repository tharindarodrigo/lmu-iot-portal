<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\IoTDashboard\Models\IoTDashboard;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\Shared\Models\Organization;
use Database\Seeders\DeviceSchemaSeeder;
use Database\Seeders\IoTDashboardSeeder;
use Database\Seeders\OrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds energy meter line, bar, and gauge dashboard widgets', function (): void {
    $this->seed([
        OrganizationSeeder::class,
        DeviceSchemaSeeder::class,
    ]);

    $organization = Organization::query()->orderBy('id')->first();
    $deviceType = DeviceType::query()->where('key', 'energy_meter')->first();
    $schemaVersion = DeviceSchemaVersion::query()
        ->whereHas('schema.deviceType', fn ($query) => $query->where('key', 'energy_meter'))
        ->where('status', 'active')
        ->orderBy('id')
        ->first();

    expect($organization)->not->toBeNull()
        ->and($deviceType)->not->toBeNull()
        ->and($schemaVersion)->not->toBeNull();

    Device::factory()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $schemaVersion->id,
        'name' => 'Seeder Test Energy Meter',
    ]);

    $this->seed(IoTDashboardSeeder::class);

    $dashboard = IoTDashboard::query()->where('slug', 'energy-meter-dashboard')->first();
    $widget = IoTDashboardWidget::query()
        ->with(['topic', 'device'])
        ->where('title', 'Energy Meter Voltages (V1 / V2 / V3)')
        ->first();

    expect($dashboard)->not->toBeNull()
        ->and($widget)->not->toBeNull()
        ->and($widget?->type)->toBe('line_chart')
        ->and($widget?->topic?->key)->toBe('telemetry')
        ->and($widget?->device)->not->toBeNull();

    $seriesKeys = collect($widget?->series_config ?? [])
        ->pluck('key')
        ->all();

    expect($seriesKeys)->toBe(['V1', 'V2', 'V3']);

    $hourlyWidget = IoTDashboardWidget::query()
        ->where('iot_dashboard_id', $dashboard?->id)
        ->where('title', 'Hourly Energy Consumption (kWh)')
        ->first();

    $dailyWidget = IoTDashboardWidget::query()
        ->where('iot_dashboard_id', $dashboard?->id)
        ->where('title', 'Daily Energy Consumption (kWh)')
        ->first();
    $gaugeWidget = IoTDashboardWidget::query()
        ->where('iot_dashboard_id', $dashboard?->id)
        ->where('title', 'Phase A Current Gauge (A1)')
        ->first();

    expect($hourlyWidget)->not->toBeNull()
        ->and($hourlyWidget?->type)->toBe('bar_chart')
        ->and(data_get($hourlyWidget?->options, 'bar_interval'))->toBe('hourly')
        ->and(collect($hourlyWidget?->series_config ?? [])->pluck('key')->all())->toBe(['total_energy_kwh'])
        ->and($dailyWidget)->not->toBeNull()
        ->and($dailyWidget?->type)->toBe('bar_chart')
        ->and(data_get($dailyWidget?->options, 'bar_interval'))->toBe('daily')
        ->and(collect($dailyWidget?->series_config ?? [])->pluck('key')->all())->toBe(['total_energy_kwh'])
        ->and($gaugeWidget)->not->toBeNull()
        ->and($gaugeWidget?->type)->toBe('gauge_chart')
        ->and(data_get($gaugeWidget?->options, 'gauge_style'))->toBe('classic')
        ->and((float) data_get($gaugeWidget?->options, 'gauge_min'))->toBe(0.0)
        ->and((float) data_get($gaugeWidget?->options, 'gauge_max'))->toBe(120.0)
        ->and(collect($gaugeWidget?->series_config ?? [])->pluck('key')->all())->toBe(['A1'])
        ->and(collect(data_get($gaugeWidget?->options, 'gauge_ranges', []))->pluck('color')->all())->toContain('#10b981', '#f59e0b', '#ef4444');
});
