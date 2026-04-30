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
use App\Domain\IoTDashboard\Widgets\SteamMeter\SteamMeterConfig;
use App\Domain\IoTDashboard\Widgets\SteamMeter\SteamMeterSnapshotResolver;
use App\Domain\Shared\Models\Organization;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use App\Filament\Admin\Pages\IoTDashboardSupport\WidgetFormOptionsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders steam meter snapshot values without graph series', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-15 10:30:00', 'UTC'));

    [$widget, $config, $topic, $steamMeter] = createSteamMeterWidgetContext();

    foreach ([
        ['2026-04-01 00:00:00', 1_000_000, 0.0],
        ['2026-04-15 00:30:00', 1_100_000, 2.5],
        ['2026-04-15 08:30:00', 1_130_000, 3.5],
        ['2026-04-15 10:30:00', 1_136_500, 4.25],
    ] as [$recordedAt, $totalisedCount, $flow]) {
        DeviceTelemetryLog::factory()
            ->forDevice($steamMeter)
            ->forTopic($topic)
            ->create([
                'recorded_at' => CarbonImmutable::parse($recordedAt, 'UTC'),
                'received_at' => CarbonImmutable::parse($recordedAt, 'UTC'),
                'transformed_values' => [
                    'flow' => $flow,
                    'totalisedCount' => $totalisedCount,
                ],
            ]);
    }

    $snapshot = app(SteamMeterSnapshotResolver::class)->resolve($widget, $config);

    expect($config->series())->toBe([])
        ->and(data_get($snapshot, 'card.total_tons'))->toBe(1137.0)
        ->and(data_get($snapshot, 'card.current_flow_rate'))->toBe(4.25)
        ->and(data_get($snapshot, 'card.monthly_kg'))->toBe(136500.0)
        ->and(data_get($snapshot, 'card.current_shift.label'))->toBe('Shift 2')
        ->and(data_get($snapshot, 'card.current_shift.kg'))->toBe(6500.0)
        ->and(data_get($snapshot, 'card.previous_shift.label'))->toBe('Shift 1')
        ->and(data_get($snapshot, 'card.previous_shift.kg'))->toBe(30000.0);

    CarbonImmutable::setTestNow();
});

it('lists only physical steam meters with flow and totalised count sources', function (): void {
    [$widget, , $topic, $steamMeter] = createSteamMeterWidgetContext();
    $dashboard = IoTDashboard::query()->findOrFail($widget->iot_dashboard_id);
    $energyMeterType = DeviceType::factory()->mqtt()->create(['key' => 'energy_meter']);
    $otherDevice = Device::factory()->create([
        'organization_id' => $steamMeter->organization_id,
        'device_type_id' => $energyMeterType->id,
        'device_schema_version_id' => $steamMeter->device_schema_version_id,
        'is_virtual' => false,
        'name' => 'Not Steam',
    ]);

    $service = app(WidgetFormOptionsService::class);
    $options = $service->steamMeterDeviceOptions($dashboard);
    $resolvedInput = $service->resolveInput($dashboard, [
        'widget_type' => WidgetType::SteamMeter->value,
        'device_id' => $steamMeter->id,
    ]);

    expect($options)->toHaveKey((string) $steamMeter->id)
        ->and($options)->not->toHaveKey((string) $otherDevice->id)
        ->and(data_get($resolvedInput, 'steam_meter_sources.flow'))->toBe([
            'device_id' => $steamMeter->id,
            'schema_version_topic_id' => $topic->id,
            'parameter_key' => 'flow',
        ])
        ->and(data_get($resolvedInput, 'steam_meter_sources.total'))->toBe([
            'device_id' => $steamMeter->id,
            'schema_version_topic_id' => $topic->id,
            'parameter_key' => 'totalisedCount',
        ]);
});

it('normalizes steam meter shift configuration', function (): void {
    $config = SteamMeterConfig::fromArray([
        'shifts' => [
            ['label' => 'Day', 'start_time' => '06:00', 'end_time' => '18:00'],
            ['label' => '', 'start_time' => '18:00', 'end_time' => '06:00'],
            ['label' => 'Invalid', 'start_time' => '24:00', 'end_time' => '06:00'],
        ],
    ]);

    expect($config->shifts())->toBe([
        ['label' => 'Day', 'start_time' => '06:00', 'end_time' => '18:00'],
        ['label' => 'Shift 2', 'start_time' => '18:00', 'end_time' => '06:00'],
    ])
        ->and($config->useWebsocket())->toBeFalse()
        ->and($config->pollingIntervalSeconds())->toBe(30);
});

function createSteamMeterWidgetContext(): array
{
    $organization = Organization::factory()->create();
    $steamMeterType = DeviceType::factory()->mqtt()->create(['key' => 'steam_meter']);
    [$schemaVersion, $topic] = createSteamMeterSchemaVersion($steamMeterType);
    $steamMeter = Device::factory()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $steamMeterType->id,
        'device_schema_version_id' => $schemaVersion->id,
        'is_virtual' => false,
        'name' => 'Main Steam Input',
        'connection_state' => 'online',
        'last_seen_at' => CarbonImmutable::now('UTC'),
    ]);
    $dashboard = IoTDashboard::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $config = SteamMeterConfig::fromArray([
        'sources' => [
            'flow' => [
                'device_id' => $steamMeter->id,
                'schema_version_topic_id' => $topic->id,
                'parameter_key' => 'flow',
            ],
            'total' => [
                'device_id' => $steamMeter->id,
                'schema_version_topic_id' => $topic->id,
                'parameter_key' => 'totalisedCount',
            ],
        ],
        'shifts' => SteamMeterConfig::defaultShifts(),
    ]);
    $widget = IoTDashboardWidget::factory()->create([
        'iot_dashboard_id' => $dashboard->id,
        'device_id' => $steamMeter->id,
        'schema_version_topic_id' => $topic->id,
        'type' => WidgetType::SteamMeter->value,
        'config' => $config->toArray(),
    ]);

    return [$widget, $config, $topic, $steamMeter];
}

/**
 * @return array{0: DeviceSchemaVersion, 1: SchemaVersionTopic}
 */
function createSteamMeterSchemaVersion(DeviceType $deviceType): array
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

    ParameterDefinition::factory()->create([
        'schema_version_topic_id' => $topic->id,
        'key' => 'flow',
        'label' => 'Flow',
        'json_path' => '$.flow',
        'type' => ParameterDataType::Decimal,
        'category' => ParameterCategory::Measurement,
        'sequence' => 1,
        'required' => true,
        'is_active' => true,
        'mutation_expression' => null,
        'validation_error_code' => null,
    ]);

    DerivedParameterDefinition::factory()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'totalisedCount',
        'label' => 'Totalised Count',
        'data_type' => ParameterDataType::Integer,
        'unit' => null,
        'expression' => [
            '+' => [
                ['*' => [['var' => 'totaliser_count_1'], 1000000]],
                ['*' => [['var' => 'totaliser_count_2'], 65536]],
                ['var' => 'totaliser_count_3'],
            ],
        ],
        'dependencies' => ['totaliser_count_1', 'totaliser_count_2', 'totaliser_count_3'],
        'json_path' => '$.totalisedCount',
    ]);

    return [$schemaVersion, $topic];
}
