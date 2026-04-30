<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\VirtualDeviceLink;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\IoTDashboard\Models\IoTDashboard;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\IoTDashboard\Widgets\LineChart\LineChartWidgetDefinition;
use App\Domain\Shared\Models\Organization;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('resolves line chart series from virtual device source links', function (): void {
    Carbon::setTestNow('2026-04-29 12:00:00');

    $organization = Organization::factory()->create();
    $statusTopic = SchemaVersionTopic::factory()->publish()->create(['key' => 'telemetry']);
    $energyTopic = SchemaVersionTopic::factory()->publish()->create(['key' => 'telemetry']);
    $virtualTopic = SchemaVersionTopic::factory()->publish()->create(['key' => 'telemetry']);

    $statusDevice = Device::factory()->create([
        'organization_id' => $organization->id,
        'device_schema_version_id' => $statusTopic->device_schema_version_id,
    ]);
    $energyDevice = Device::factory()->create([
        'organization_id' => $organization->id,
        'device_schema_version_id' => $energyTopic->device_schema_version_id,
    ]);
    $virtualDevice = Device::factory()->virtual()->create([
        'organization_id' => $organization->id,
        'device_schema_version_id' => $virtualTopic->device_schema_version_id,
    ]);

    VirtualDeviceLink::factory()->create([
        'virtual_device_id' => $virtualDevice->id,
        'source_device_id' => $statusDevice->id,
        'purpose' => 'status',
    ]);
    VirtualDeviceLink::factory()->create([
        'virtual_device_id' => $virtualDevice->id,
        'source_device_id' => $energyDevice->id,
        'purpose' => 'energy',
    ]);

    DeviceTelemetryLog::factory()
        ->forDevice($statusDevice)
        ->forTopic($statusTopic)
        ->create([
            'recorded_at' => now()->subMinutes(2),
            'received_at' => now()->subMinutes(2),
            'transformed_values' => ['status' => 1],
        ]);

    DeviceTelemetryLog::factory()
        ->forDevice($energyDevice)
        ->forTopic($energyTopic)
        ->create([
            'recorded_at' => now()->subMinute(),
            'received_at' => now()->subMinute(),
            'transformed_values' => ['PhaseACurrent' => 12.5],
        ]);

    $dashboard = IoTDashboard::factory()->for($organization)->create();
    $widget = IoTDashboardWidget::factory()->create([
        'iot_dashboard_id' => $dashboard->id,
        'device_id' => $virtualDevice->id,
        'schema_version_topic_id' => $virtualTopic->id,
        'config' => [
            'series' => [
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'color' => '#64748b',
                    'source' => ['type' => 'virtual_device_link', 'purpose' => 'status'],
                ],
                [
                    'key' => 'PhaseACurrent',
                    'label' => 'Phase A Current',
                    'color' => '#22d3ee',
                    'source' => ['type' => 'virtual_device_link', 'purpose' => 'energy'],
                ],
            ],
            'window' => [
                'lookback_minutes' => 60,
                'max_points' => 60,
            ],
        ],
    ]);

    $snapshot = app(LineChartWidgetDefinition::class)->resolveSnapshot($widget->fresh());
    $seriesByKey = collect($snapshot['series'])->keyBy('key');

    expect($seriesByKey->get('status')['points'])->toHaveCount(1)
        ->and($seriesByKey->get('status')['points'][0]['value'])->toBe(1)
        ->and($seriesByKey->get('PhaseACurrent')['points'])->toHaveCount(1)
        ->and($seriesByKey->get('PhaseACurrent')['points'][0]['value'])->toBe(12.5);
});
