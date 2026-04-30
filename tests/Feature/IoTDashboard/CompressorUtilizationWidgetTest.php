<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Enums\ParameterCategory;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\DerivedParameterDefinition;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\IoTDashboard\Enums\WidgetType;
use App\Domain\IoTDashboard\Models\IoTDashboard;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\IoTDashboard\Widgets\CompressorUtilization\CompressorUtilizationConfig;
use App\Domain\IoTDashboard\Widgets\CompressorUtilization\CompressorUtilizationSnapshotResolver;
use App\Domain\Shared\Models\Organization;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use App\Filament\Admin\Pages\IoTDashboardSupport\WidgetFormOptionsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calculates compressor utilization from the derived status parameter within the current shift', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-15 02:30:00', 'UTC'));

    [$widget, $config, $topic, $compressor] = createCompressorUtilizationSnapshotContext();

    foreach ([
        ['2026-04-15 00:00:00', 1],
        ['2026-04-15 01:30:00', 0],
        ['2026-04-15 02:00:00', 1],
    ] as [$recordedAt, $status]) {
        DeviceTelemetryLog::factory()
            ->forDevice($compressor)
            ->forTopic($topic)
            ->create([
                'recorded_at' => CarbonImmutable::parse($recordedAt, 'UTC'),
                'received_at' => CarbonImmutable::parse($recordedAt, 'UTC'),
                'transformed_values' => ['status' => $status],
            ]);
    }

    $snapshot = app(CompressorUtilizationSnapshotResolver::class)->resolve($widget, $config);

    expect(data_get($snapshot, 'card.state.label'))->toBe('Running')
        ->and(data_get($snapshot, 'card.state.is_running'))->toBeTrue()
        ->and(data_get($snapshot, 'card.current_shift.label'))->toBe('Shift 1')
        ->and(data_get($snapshot, 'card.current_shift.utilization_percent'))->toBe(75.0)
        ->and(data_get($snapshot, 'card.current_shift.run_minutes'))->toBe(90.0)
        ->and(data_get($snapshot, 'card.current_shift.idle_minutes'))->toBe(30.0)
        ->and(data_get($snapshot, 'card.percentage_thresholds'))->toBe(CompressorUtilizationConfig::defaultPercentageThresholds())
        ->and(data_get($snapshot, 'card.status_segments'))->toHaveCount(2)
        ->and(data_get($snapshot, 'card.status_segments.0.state'))->toBe('off')
        ->and(data_get($snapshot, 'card.status_segments.1.state'))->toBe('on')
        ->and(data_get($snapshot, 'card.daily_utilizations'))->toHaveCount(3);

    CarbonImmutable::setTestNow();
});

it('falls back to phase a current when derived status is not persisted', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-15 02:30:00', 'UTC'));

    [$widget, $config, $topic, $compressor] = createCompressorUtilizationSnapshotContext();

    foreach ([
        ['2026-04-15 00:00:00', 12],
        ['2026-04-15 01:30:00', 0],
        ['2026-04-15 02:00:00', 11],
    ] as [$recordedAt, $phaseACurrent]) {
        DeviceTelemetryLog::factory()
            ->forDevice($compressor)
            ->forTopic($topic)
            ->create([
                'recorded_at' => CarbonImmutable::parse($recordedAt, 'UTC'),
                'received_at' => CarbonImmutable::parse($recordedAt, 'UTC'),
                'transformed_values' => ['PhaseACurrent' => $phaseACurrent],
            ]);
    }

    $snapshot = app(CompressorUtilizationSnapshotResolver::class)->resolve($widget, $config);

    expect(data_get($snapshot, 'card.state.label'))->toBe('Running')
        ->and(data_get($snapshot, 'card.current_shift.utilization_percent'))->toBe(75.0)
        ->and(data_get($snapshot, 'card.current_shift.run_minutes'))->toBe(90.0)
        ->and(data_get($snapshot, 'card.current_shift.idle_minutes'))->toBe(30.0);

    CarbonImmutable::setTestNow();
});

it('lists only energy meter compressors with a derived status parameter and resolves the status source', function (): void {
    [$widget, , $topic, $compressor] = createCompressorUtilizationSnapshotContext();
    $dashboard = IoTDashboard::query()->findOrFail($widget->iot_dashboard_id);
    $energyMeterType = DeviceType::query()->where('key', 'energy_meter')->firstOrFail();
    [$standardSchemaVersion] = createCompressorSchemaVersion($energyMeterType, false);

    $standardEnergyMeter = Device::factory()->create([
        'organization_id' => $compressor->organization_id,
        'device_type_id' => $energyMeterType->id,
        'device_schema_version_id' => $standardSchemaVersion->id,
        'is_virtual' => false,
        'name' => 'TJ-Main Energy',
    ]);

    $service = app(WidgetFormOptionsService::class);
    $options = $service->compressorDeviceOptions($dashboard);
    $resolvedInput = $service->resolveInput($dashboard, [
        'widget_type' => WidgetType::CompressorUtilization->value,
        'device_id' => $compressor->id,
    ]);

    expect($options)->toHaveKey((string) $compressor->id)
        ->and($options)->not->toHaveKey((string) $standardEnergyMeter->id)
        ->and(data_get($resolvedInput, 'compressor_sources.status'))->toBe([
            'device_id' => $compressor->id,
            'schema_version_topic_id' => $topic->id,
            'parameter_key' => 'status',
        ]);
});

it('normalizes compressor shift and threshold configuration', function (): void {
    $config = CompressorUtilizationConfig::fromArray([
        'shifts' => [
            ['label' => 'Day', 'start_time' => '06:00', 'end_time' => '18:00'],
            ['label' => '', 'start_time' => '18:00', 'end_time' => '06:00'],
            ['label' => 'Invalid', 'start_time' => '25:00', 'end_time' => '06:00'],
        ],
        'percentage_thresholds' => [
            ['label' => 'Low', 'minimum' => '0', 'maximum' => '49.5', 'color' => '#FF0000'],
            ['label' => 'Invalid', 'minimum' => '90', 'maximum' => '80', 'color' => '#000000'],
            ['label' => '', 'minimum' => '50', 'maximum' => '100', 'color' => '#00AA00'],
        ],
    ]);

    expect($config->shifts())->toBe([
        ['label' => 'Day', 'start_time' => '06:00', 'end_time' => '18:00'],
        ['label' => 'Shift 2', 'start_time' => '18:00', 'end_time' => '06:00'],
    ])
        ->and($config->percentageThresholds())->toBe([
            ['label' => 'Low', 'minimum' => 0.0, 'maximum' => 49.5, 'color' => '#ff0000'],
            ['label' => 'Threshold 3', 'minimum' => 50.0, 'maximum' => 100.0, 'color' => '#00aa00'],
        ])
        ->and($config->useWebsocket())->toBeFalse()
        ->and($config->pollingIntervalSeconds())->toBe(30);
});

function createCompressorUtilizationSnapshotContext(): array
{
    $organization = Organization::factory()->create();
    $energyMeterType = DeviceType::factory()->mqtt()->create(['key' => 'energy_meter']);
    [$schemaVersion, $topic] = createCompressorSchemaVersion($energyMeterType, true);
    $compressor = Device::factory()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $energyMeterType->id,
        'device_schema_version_id' => $schemaVersion->id,
        'is_virtual' => false,
        'name' => 'TJ-Compressor01',
        'connection_state' => 'online',
        'last_seen_at' => CarbonImmutable::now('UTC'),
    ]);
    $dashboard = IoTDashboard::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $config = CompressorUtilizationConfig::fromArray([
        'sources' => [
            'status' => [
                'device_id' => $compressor->id,
                'schema_version_topic_id' => $topic->id,
                'parameter_key' => 'status',
            ],
        ],
        'shifts' => CompressorUtilizationConfig::defaultShifts(),
    ]);
    $widget = IoTDashboardWidget::factory()->create([
        'iot_dashboard_id' => $dashboard->id,
        'device_id' => $compressor->id,
        'schema_version_topic_id' => $topic->id,
        'type' => WidgetType::CompressorUtilization->value,
        'config' => $config->toArray(),
    ]);

    return [$widget, $config, $topic, $compressor];
}

/**
 * @return array{0: DeviceSchemaVersion, 1: SchemaVersionTopic}
 */
function createCompressorSchemaVersion(DeviceType $deviceType, bool $withDerivedStatus): array
{
    $schema = DeviceSchema::factory()->forDeviceType($deviceType)->create();
    $schemaVersion = DeviceSchemaVersion::factory()->active()->create([
        'device_schema_id' => $schema->id,
    ]);
    $topic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'telemetry',
        'suffix' => 'telemetry',
        'label' => 'Telemetry',
    ]);

    foreach (['PhaseACurrent', 'TotalEnergy'] as $sequence => $key) {
        ParameterDefinition::factory()->create([
            'schema_version_topic_id' => $topic->id,
            'key' => $key,
            'label' => $key,
            'json_path' => '$.'.$key,
            'type' => ParameterDataType::Decimal,
            'category' => $key === 'TotalEnergy' ? ParameterCategory::Counter : ParameterCategory::Measurement,
            'sequence' => $sequence + 1,
            'required' => true,
            'is_active' => true,
            'mutation_expression' => null,
            'validation_error_code' => null,
        ]);
    }

    if ($withDerivedStatus) {
        DerivedParameterDefinition::factory()->create([
            'device_schema_version_id' => $schemaVersion->id,
            'key' => 'status',
            'label' => 'Status',
            'data_type' => ParameterDataType::Integer,
            'unit' => null,
            'expression' => [
                'if' => [
                    ['>' => [['var' => 'PhaseACurrent'], 10]],
                    1,
                    0,
                ],
            ],
            'dependencies' => ['PhaseACurrent'],
            'json_path' => '$.status',
        ]);
    }

    return [$schemaVersion, $topic];
}
