<?php

declare(strict_types=1);

use App\Domain\DataIngestion\DTO\IncomingTelemetryEnvelope;
use App\Domain\DataIngestion\Jobs\ProcessInboundTelemetryJob;
use App\Domain\DataIngestion\Models\DeviceSignalBinding;
use App\Domain\DataIngestion\Services\DeviceSignalBindingResolver;
use App\Domain\DataIngestion\Services\TelemetryIngestionService;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Models\DerivedParameterDefinition;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\DeviceSchema\Services\JsonLogicEvaluator;
use App\Domain\Shared\Models\Organization;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use Database\Seeders\TeejayAcEnergyMateSeeder;
use Database\Seeders\TeejayFabricLengthSeeder;
use Database\Seeders\TeejayFabricLengthShortSeeder;
use Database\Seeders\TeejayHubsSeeder;
use Database\Seeders\TeejayMigrationSeeder;
use Database\Seeders\TeejayModbusLevelSensorSeeder;
use Database\Seeders\TeejayPressureSeeder;
use Database\Seeders\TeejayStatusSeeder;
use Database\Seeders\TeejaySteamMeterSeeder;
use Database\Seeders\TeejayStenterSeeder;
use Database\Seeders\TeejayTemperatureSeeder;
use Database\Seeders\TeejayWaterFlowVolumeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'broadcasting.default' => 'null',
        'cache.default' => 'array',
        'iot.presence.write_throttle_seconds' => 0,
    ]);
});

function ingestSourceTelemetry(string $sourceTopic, array $payload): void
{
    $job = new ProcessInboundTelemetryJob((new IncomingTelemetryEnvelope(
        sourceSubject: str_replace('/', '.', $sourceTopic),
        mqttTopic: $sourceTopic,
        payload: $payload,
        receivedAt: Carbon::now(),
    ))->toArray());

    $job->handle(app(TelemetryIngestionService::class), app(DeviceSignalBindingResolver::class));
}

it('evaluates both big-endian float mutation operator aliases', function (): void {
    $value = 4675957583454314000;
    $evaluator = new JsonLogicEvaluator;

    expect($evaluator->evaluate([
        'decode_big_endian_float' => [
            ['var' => 'val'],
            8,
        ],
    ], ['val' => $value]))->toEqualWithDelta(41653.182933148, 0.0001)
        ->and($evaluator->evaluate([
            'reinterpret_big_endian_float' => [
                ['var' => 'val'],
                8,
            ],
        ], ['val' => $value]))->toEqualWithDelta(41653.182933148, 0.0001);
});

it('seeds the full teejay migration inventory through the orchestrator', function (): void {
    seed(TeejayMigrationSeeder::class);
    seed(TeejayMigrationSeeder::class);

    $organization = Organization::query()
        ->where('slug', TeejayMigrationSeeder::ORGANIZATION_SLUG)
        ->first();

    expect($organization)->not->toBeNull();

    $devices = Device::query()
        ->where('organization_id', $organization?->id)
        ->get();

    $countsByMigrationType = $devices
        ->groupBy(fn (Device $device): string => (string) ($device->metadata['migration_device_type'] ?? 'unknown'))
        ->map(fn ($groupedDevices): int => $groupedDevices->count())
        ->all();

    $rfDryer = $devices->firstWhere('name', 'RF Dryer');
    $dieselTank = $devices->firstWhere('name', 'Tank 5 - Diesel');
    $stenter07Status = $devices->firstWhere('name', 'TJ-Stenter07 Status');
    $stenter07 = $devices->firstWhere('name', 'TJ - Stenter07 (AGR)');

    expect($devices)->toHaveCount(260)
        ->and($countsByMigrationType)->toMatchArray([
            'IMoni Hub' => 33,
            'AC Energy Mate' => 98,
            'Water Flow and Volume' => 28,
            'IMoni Modbus Level Sensor' => 9,
            'Fabric Length' => 14,
            'Fabric Length(Short)' => 8,
            'Status' => 25,
            'Temperature' => 16,
            'Steam meter' => 11,
            'Preassure' => 8,
            'Stenter' => 10,
        ])
        ->and($rfDryer?->external_id)->toBe('869604063870249-2E')
        ->and($dieselTank?->external_id)->toBe('869604063845217-55')
        ->and($stenter07Status?->external_id)->toBe('869604063852510-00-2')
        ->and($stenter07?->metadata['components'] ?? [])->toContainEqual([
            'label' => 'length',
            'component_type' => 'Fabric Length(Short)',
            'component_name' => 'TJ-Stenter07 Fabric Length',
            'component_external_id' => '869604063852510-52-2',
        ]);
});

it('seeds teejay hubs with canonical source imeis only', function (): void {
    seed(TeejayHubsSeeder::class);

    $organization = Organization::query()
        ->where('slug', TeejayMigrationSeeder::ORGANIZATION_SLUG)
        ->firstOrFail();

    $hubExternalIds = Device::query()
        ->where('organization_id', $organization->id)
        ->whereNull('parent_device_id')
        ->orderBy('external_id')
        ->pluck('external_id')
        ->all();

    expect($hubExternalIds)->toHaveCount(33)
        ->and($hubExternalIds)->toContain('8865286073149329')
        ->and($hubExternalIds)->toContain('869604063866593')
        ->and($hubExternalIds)->not->toContain('865286073149329')
        ->and($hubExternalIds)->not->toContain('869467047530150');
});

it('seeds teejay ac energy meters with normalized numeric bindings and standard scaling', function (): void {
    seed(TeejayAcEnergyMateSeeder::class);

    $device = Device::query()->where('name', 'RF Dryer')->firstOrFail();
    $telemetryTopic = $device->schemaVersion?->topics()->where('key', 'telemetry')->first();
    $energyParameter = $telemetryTopic?->parameters()->where('key', 'TotalEnergy')->first();
    $energyBinding = DeviceSignalBinding::query()
        ->where('device_id', $device->id)
        ->where('parameter_definition_id', $energyParameter?->id)
        ->first();

    expect(Device::query()->whereHas('deviceType', fn ($query) => $query->where('key', 'energy_meter'))->count())->toBeGreaterThanOrEqual(98)
        ->and($device->external_id)->toBe('869604063870249-2E')
        ->and($energyBinding?->source_topic)->toBe('migration/source/imoni/869604063870249/2E/telemetry')
        ->and($energyBinding?->source_json_path)->toBe('$.io_7_value')
        ->and($energyParameter?->getAttribute('mutation_expression'))->toMatchArray([
            '/' => [
                ['var' => 'val'],
                1000,
            ],
        ]);
});

it('ingests teejay ac energy telemetry into scaled engineering values', function (): void {
    seed(TeejayAcEnergyMateSeeder::class);

    $device = Device::query()->where('name', 'RF Dryer')->firstOrFail();
    $sourceTopic = 'migration/source/imoni/869604063870249/2E/telemetry';

    ingestSourceTelemetry($sourceTopic, [
        'peripheral_name' => 'AC_energyMate14',
        'peripheral_type_hex' => '2E',
        'io_1_value' => 2300,
        'io_2_value' => 2310,
        'io_3_value' => 2290,
        'io_4_value' => 1200,
        'io_5_value' => 1100,
        'io_6_value' => 1000,
        'io_7_value' => 450000,
        'io_8_value' => 0.96,
    ]);

    $telemetryLog = $device->fresh()->telemetryLogs()->latest('id')->first();

    expect($telemetryLog?->transformed_values)->toMatchArray([
        'TotalEnergy' => 450.0,
        'PhaseAVoltage' => 230.0,
        'PhaseBVoltage' => 231.0,
        'PhaseCVoltage' => 229.0,
        'PhaseACurrent' => 12.0,
        'PhaseBCurrent' => 11.0,
        'PhaseCCurrent' => 10.0,
        'totalPowerFactor' => 0.96,
    ]);
});

it('prunes unused teejay ac energy draft schema versions while preserving historically used drafts', function (): void {
    seed(TeejayAcEnergyMateSeeder::class);

    $schema = DeviceSchema::query()
        ->where('name', 'Energy Meter Contract')
        ->whereHas('deviceType', fn ($query) => $query->where('key', 'energy_meter'))
        ->firstOrFail();

    $unusedDraftVersion = DeviceSchemaVersion::factory()->create([
        'device_schema_id' => $schema->id,
        'version' => 998,
        'status' => 'draft',
        'notes' => 'Unused recovered draft variant',
    ]);

    $historicalDraftVersion = DeviceSchemaVersion::factory()->create([
        'device_schema_id' => $schema->id,
        'version' => 999,
        'status' => 'draft',
        'notes' => 'Historical recovered draft variant',
    ]);

    $historicalTopic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $historicalDraftVersion->id,
        'key' => 'telemetry',
        'label' => 'Telemetry',
        'suffix' => 'telemetry',
        'sequence' => 0,
    ]);

    $historicalDevice = Device::query()->where('name', 'RF Dryer')->firstOrFail();

    DeviceTelemetryLog::factory()
        ->forDevice($historicalDevice)
        ->forTopic($historicalTopic)
        ->create();

    seed(TeejayAcEnergyMateSeeder::class);

    expect(DeviceSchemaVersion::query()->whereKey($unusedDraftVersion->id)->exists())->toBeFalse()
        ->and(DeviceSchemaVersion::query()->whereKey($historicalDraftVersion->id)->exists())->toBeTrue()
        ->and(Device::query()->where('device_schema_version_id', $historicalDraftVersion->id)->exists())->toBeFalse()
        ->and(DeviceTelemetryLog::query()->where('device_schema_version_id', $historicalDraftVersion->id)->exists())->toBeTrue();
});

it('seeds teejay water flow devices with raw-hex decode metadata on the special hubs', function (): void {
    seed(TeejayWaterFlowVolumeSeeder::class);

    $device = Device::query()->where('name', 'TJ-V90Drain')->firstOrFail();
    $flowBinding = DeviceSignalBinding::query()
        ->where('device_id', $device->id)
        ->where('source_json_path', '$.io_1_value')
        ->first();
    $volumeBinding = DeviceSignalBinding::query()
        ->where('device_id', $device->id)
        ->where('source_json_path', '$.io_2_value')
        ->first();

    expect(Device::query()->whereHas('deviceType', fn ($query) => $query->where('key', 'water_flow_meter'))->count())->toBe(28)
        ->and($flowBinding?->metadata['decoder']['mode'] ?? null)->toBe('bigEndianFloat32')
        ->and($flowBinding?->metadata['decoder']['strip_prefix_bytes'] ?? null)->toBe(2)
        ->and($volumeBinding?->metadata['decoder']['mode'] ?? null)->toBe('bigEndianFloatAuto')
        ->and($volumeBinding?->metadata['decoder']['strip_prefix_bytes'] ?? null)->toBe(2)
        ->and($device->schemaVersion?->topics()->where('key', 'telemetry')->first()?->parameters()->where('key', 'volume')->first()?->getAttribute('mutation_expression'))->toMatchArray([
            'if' => [
                ['>' => [
                    ['var' => 'val'],
                    4294967295,
                ]],
                ['decode_big_endian_float' => [
                    ['var' => 'val'],
                    8,
                ]],
                ['var' => 'val'],
            ],
        ]);
});

it('ingests teejay water flow telemetry using the laravel-side big-endian float decoder', function (): void {
    seed(TeejayWaterFlowVolumeSeeder::class);

    $device = Device::query()->where('name', 'TJ-V90Drain')->firstOrFail();
    $sourceTopic = 'migration/source/imoni/869604063871346/51/telemetry';

    ingestSourceTelemetry($sourceTopic, [
        'peripheral_name' => 'Modbus1',
        'peripheral_type_hex' => '51',
        'io_1_value' => 999999,
        'io_1_raw_hex' => '01013FC00000',
        'io_2_value' => 999999,
        'io_2_raw_hex' => '010240100000',
    ]);

    $telemetryLog = $device->fresh()->telemetryLogs()->latest('id')->first();

    expect($telemetryLog?->transformed_values['flow'] ?? null)->toEqualWithDelta(5400.0, 0.0001)
        ->and($telemetryLog?->transformed_values['volume'] ?? null)->toEqualWithDelta(2.25, 0.0001);
});

it('ingests teejay water flow telemetry when volume arrives as an 8-byte big-endian value', function (): void {
    seed(TeejayWaterFlowVolumeSeeder::class);

    $device = Device::query()->where('name', 'TJ-Thickener in')->firstOrFail();
    $sourceTopic = 'migration/source/imoni/869604063849748/51/telemetry';

    ingestSourceTelemetry($sourceTopic, [
        'peripheral_name' => 'Modbus1',
        'peripheral_type_hex' => '51',
        'io_1_value' => 999999,
        'io_1_raw_hex' => '01013FC00000',
        'io_2_value' => 4689390327806706000,
        'io_2_raw_hex' => '01024002000000000000',
    ]);

    $telemetryLog = $device->fresh()->telemetryLogs()->latest('id')->first();

    expect($telemetryLog?->transformed_values['flow'] ?? null)->toEqualWithDelta(5400.0, 0.0001)
        ->and($telemetryLog?->transformed_values['volume'] ?? null)->toEqualWithDelta(2.25, 0.0001);
});

it('ingests teejay water flow telemetry when volume arrives as a 64-bit float bit-pattern integer', function (): void {
    seed(TeejayWaterFlowVolumeSeeder::class);

    $device = Device::query()->where('external_id', '869604063870249-53')->firstOrFail();
    $sourceTopic = 'migration/source/imoni/869604063870249/53/telemetry';

    ingestSourceTelemetry($sourceTopic, [
        'peripheral_name' => 'Modbus3',
        'peripheral_type_hex' => '53',
        'io_1_value' => 0,
        'io_2_value' => 4675957539219998000,
    ]);

    $telemetryLog = $device->fresh()->telemetryLogs()->latest('id')->first();

    expect($telemetryLog?->transformed_values['flow'] ?? null)->toEqual(0.0)
        ->and($telemetryLog?->transformed_values['volume'] ?? null)->toEqualWithDelta(41652.861086139805, 0.0001);
});

it('seeds teejay modbus level sensors with canonicalized child ids and recovered calibrations', function (): void {
    seed(TeejayModbusLevelSensorSeeder::class);

    $dieselTank = Device::query()->where('name', 'Tank 5 - Diesel')->firstOrFail();
    $telemetryTopic = $dieselTank->schemaVersion?->topics()->where('key', 'telemetry')->first();
    $level2Parameter = $telemetryTopic?->parameters()->where('key', 'level2')->first();
    $binding = DeviceSignalBinding::query()
        ->where('device_id', $dieselTank->id)
        ->where('source_json_path', '$.io_1_value')
        ->first();

    expect($dieselTank->external_id)->toBe('869604063845217-55')
        ->and($binding?->metadata['decoder']['mode'] ?? null)->toBe('bigEndianFloat32')
        ->and($level2Parameter?->getAttribute('mutation_expression'))->toMatchArray([
            '-' => [
                ['*' => [
                    ['var' => 'val'],
                    0.0032808,
                ]],
                2.3,
            ],
        ]);
});

it('seeds teejay fabric length devices with twos-complement decoding on online inspection lines', function (): void {
    seed(TeejayFabricLengthSeeder::class);

    $device = Device::query()->where('name', 'TJ-Online-inspection-1')->firstOrFail();
    $binding = DeviceSignalBinding::query()->where('device_id', $device->id)->first();

    expect($device->external_id)->toBe('869604063859564-51')
        ->and($binding?->metadata['decoder']['mode'] ?? null)->toBe('twosComplement')
        ->and($binding?->source_json_path)->toBe('$.io_1_value');
});

it('ingests teejay fabric length telemetry using the laravel-side twos-complement decoder', function (): void {
    seed(TeejayFabricLengthSeeder::class);

    $device = Device::query()->where('name', 'TJ-Online-inspection-1')->firstOrFail();
    $sourceTopic = 'migration/source/imoni/869604063859564/51/telemetry';

    ingestSourceTelemetry($sourceTopic, [
        'peripheral_name' => 'Modbus1',
        'peripheral_type_hex' => '51',
        'io_1_value' => 999,
        'io_1_raw_hex' => '0101FF9C',
    ]);

    $telemetryLog = $device->fresh()->telemetryLogs()->latest('id')->first();

    expect($telemetryLog?->transformed_values['length'] ?? null)->toEqualWithDelta(-100.0, 0.0001);
});

it('seeds teejay short fabric length devices with distinct logical ids on shared peripherals', function (): void {
    seed(TeejayFabricLengthShortSeeder::class);

    $stenter06 = Device::query()->where('name', 'TJ-Stenter06 Fabric Length')->firstOrFail();
    $stenter07 = Device::query()->where('name', 'TJ-Stenter07 Fabric Length')->firstOrFail();
    $onlineInspection = Device::query()->where('name', 'TJ-Online-inspection-4')->firstOrFail();
    $binding = DeviceSignalBinding::query()->where('device_id', $onlineInspection->id)->first();

    expect($stenter06->external_id)->toBe('869604063852510-52-1')
        ->and($stenter07->external_id)->toBe('869604063852510-52-2')
        ->and($binding?->source_json_path)->toBe('$.io_1_value');
});

it('seeds teejay status devices with special comparison logic and distinct shared-peripheral ids', function (): void {
    seed(TeejayStatusSeeder::class);

    $stenter02Status = Device::query()->where('name', 'TJ-Stenter02  Status')->firstOrFail();
    $stenter06Status = Device::query()->where('name', 'TJ-Stenter06 Status')->firstOrFail();
    $stenter07Status = Device::query()->where('name', 'TJ-Stenter07 Status')->firstOrFail();
    $stenter08Status = Device::query()->where('name', 'TJ-Stenter08 Status')->firstOrFail();
    $schema = DeviceSchema::query()
        ->where('name', 'Status')
        ->whereHas('deviceType', fn ($query) => $query->where('key', 'status'))
        ->firstOrFail();
    $statusSchemaVersions = $schema->versions()->get();
    $binding = DeviceSignalBinding::query()
        ->where('device_id', $stenter02Status->id)
        ->first();

    expect($stenter02Status->external_id)->toBe('869604063871403-51')
        ->and($stenter06Status->external_id)->toBe('869604063852510-00-1')
        ->and($stenter07Status->external_id)->toBe('869604063852510-00-2')
        ->and($stenter08Status->external_id)->toBe('869604063852510-00-3')
        ->and($statusSchemaVersions)->toHaveCount(1)
        ->and($statusSchemaVersions->first()?->status)->toBe('active')
        ->and($binding?->metadata['mutation_expression'] ?? null)->toMatchArray([
            'if' => [
                [
                    '==' => [
                        ['var' => 'val'],
                        362,
                    ],
                ],
                0,
                1,
            ],
        ]);
});

it('ingests teejay stenter status telemetry with the recovered compare-to-362 logic', function (): void {
    seed(TeejayStatusSeeder::class);

    $device = Device::query()->where('name', 'TJ-Stenter02  Status')->firstOrFail();
    $sourceTopic = 'migration/source/imoni/869604063871403/51/telemetry';

    ingestSourceTelemetry($sourceTopic, [
        'peripheral_name' => 'Modbus1',
        'peripheral_type_hex' => '51',
        'io_1_value' => 362,
    ]);

    $telemetryLog = $device->fresh()->telemetryLogs()->latest('id')->first();

    expect($telemetryLog?->transformed_values['status'] ?? null)->toBe(0);
});

it('seeds teejay temperature variants from the legacy divide-by-10 rules', function (): void {
    seed(TeejayTemperatureSeeder::class);

    $device = Device::query()->where('name', 'Hot water Supply')->firstOrFail();
    $telemetryTopic = $device->schemaVersion?->topics()->where('key', 'telemetry')->first();
    $parameter = $telemetryTopic?->parameters()->where('key', 'temperature')->first();

    expect($parameter?->getAttribute('mutation_expression'))->toMatchArray([
        '/' => [
            ['var' => 'val'],
            10.0,
        ],
    ]);
});

it('seeds teejay steam meters with the derived totalised count parameter', function (): void {
    seed(TeejaySteamMeterSeeder::class);

    $device = Device::query()->where('name', 'TJ-VAM Chiller')->firstOrFail();
    $derivedParameter = DerivedParameterDefinition::query()
        ->where('device_schema_version_id', $device->device_schema_version_id)
        ->where('key', 'totalisedCount')
        ->first();

    expect($derivedParameter)->not->toBeNull()
        ->and($derivedParameter?->resolvedDependencies())->toBe([
            'totaliser_count_1',
            'totaliser_count_2',
            'totaliser_count_3',
        ]);
});

it('ingests teejay steam meter telemetry with flow scaling and derived totalisers', function (): void {
    seed(TeejaySteamMeterSeeder::class);

    $device = Device::query()->where('name', 'TJ-VAM Chiller')->firstOrFail();
    $sourceTopic = 'migration/source/imoni/869604063871346/57/telemetry';

    ingestSourceTelemetry($sourceTopic, [
        'peripheral_name' => 'Modbus7',
        'peripheral_type_hex' => '57',
        'io_1_value' => 1234,
        'io_2_value' => 2,
        'io_3_value' => 3,
        'io_4_value' => 4,
    ]);

    $telemetryLog = $device->fresh()->telemetryLogs()->latest('id')->first();

    expect($telemetryLog?->transformed_values['flow'] ?? null)->toEqualWithDelta(12.34, 0.0001)
        ->and($telemetryLog?->transformed_values['totalisedCount'] ?? null)->toBe(2196612);
});

it('seeds teejay pressure variants from both calibrated and conditional legacy rules', function (): void {
    seed(TeejayPressureSeeder::class);

    $steamHigh = Device::query()->where('name', 'Steam - High')->firstOrFail();
    $miniPadder = Device::query()->where('name', 'Slitter 03 Mini Padder')->firstOrFail();

    $steamParameter = $steamHigh->schemaVersion?->topics()->where('key', 'telemetry')->first()?->parameters()->where('key', 'pressure')->first();
    $padderParameter = $miniPadder->schemaVersion?->topics()->where('key', 'telemetry')->first()?->parameters()->where('key', 'pressure')->first();

    expect($steamParameter?->getAttribute('mutation_expression'))->toMatchArray([
        '/' => [
            ['var' => 'val'],
            100.0,
        ],
    ])->and($padderParameter?->getAttribute('mutation_expression'))->toMatchArray([
        '/' => [
            ['var' => 'val'],
            10,
        ],
    ]);
});

it('seeds teejay stenter aggregate devices with canonical component references', function (): void {
    seed([
        TeejayAcEnergyMateSeeder::class,
        TeejayFabricLengthSeeder::class,
        TeejayFabricLengthShortSeeder::class,
        TeejayStatusSeeder::class,
        TeejayStenterSeeder::class,
    ]);

    $stenter = Device::query()->where('name', 'TJ - Stenter06 (AGR)')->firstOrFail();

    expect($stenter->metadata['components'] ?? [])->toContainEqual([
        'label' => 'energy',
        'component_type' => 'AC Energy Mate',
        'component_name' => 'TJ-Stenter06',
        'component_external_id' => '869604063852510-21',
    ])->and($stenter->metadata['components'] ?? [])->toContainEqual([
        'label' => 'status',
        'component_type' => 'Status',
        'component_name' => 'TJ-Stenter06 Status',
        'component_external_id' => '869604063852510-00-1',
    ])->and($stenter->metadata['components'] ?? [])->toContainEqual([
        'label' => 'length',
        'component_type' => 'Fabric Length(Short)',
        'component_name' => 'TJ-Stenter06 Fabric Length',
        'component_external_id' => '869604063852510-52-1',
    ]);
});
