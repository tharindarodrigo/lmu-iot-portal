<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\DeviceTypes\Enums\ProtocolType;
use App\Domain\DeviceTypes\Models\DeviceType;
use App\Domain\DeviceTypes\ValueObjects\Protocol\MqttProtocolConfig;
use App\Domain\IoT\Enums\ParameterDataType;
use App\Domain\IoT\Models\DerivedParameterDefinition;
use App\Domain\IoT\Models\DeviceSchema;
use App\Domain\IoT\Models\DeviceSchemaVersion;
use App\Domain\IoT\Models\ParameterDefinition;
use Illuminate\Database\Seeder;

class DeviceSchemaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $thermalDeviceType = DeviceType::firstOrCreate(
            ['key' => 'thermal_sensor'],
            [
                'organization_id' => null,
                'name' => 'Thermal Sensor',
                'default_protocol' => ProtocolType::Mqtt,
                'protocol_config' => (new MqttProtocolConfig(
                    brokerHost: 'mqtt.iot-platform.local',
                    brokerPort: 1883,
                    username: 'thermal_sensor',
                    password: 'thermal_password',
                    useTls: false,
                    telemetryTopicTemplate: 'thermal/:device_uuid/telemetry',
                    controlTopicTemplate: 'thermal/:device_uuid/control',
                    qos: 1,
                    retain: false,
                ))->toArray(),
            ]
        );

        $networkDeviceType = DeviceType::firstOrCreate(
            ['key' => 'network_gateway'],
            [
                'organization_id' => null,
                'name' => 'Network Gateway',
                'default_protocol' => ProtocolType::Mqtt,
                'protocol_config' => (new MqttProtocolConfig(
                    brokerHost: 'mqtt.iot-platform.local',
                    brokerPort: 1883,
                    username: 'network_gateway',
                    password: 'network_password',
                    useTls: false,
                    telemetryTopicTemplate: 'network/:device_uuid/telemetry',
                    controlTopicTemplate: 'network/:device_uuid/control',
                    qos: 1,
                    retain: false,
                ))->toArray(),
            ]
        );

        $energyMeterType = DeviceType::firstOrCreate(
            ['key' => 'energy_meter'],
            [
                'organization_id' => null,
                'name' => 'Energy Meter',
                'default_protocol' => ProtocolType::Mqtt,
                'protocol_config' => (new MqttProtocolConfig(
                    brokerHost: 'mqtt.iot-platform.local',
                    brokerPort: 1883,
                    username: 'energy_meter',
                    password: 'energy_password',
                    useTls: false,
                    telemetryTopicTemplate: 'energy/:device_uuid/telemetry',
                    controlTopicTemplate: 'energy/:device_uuid/control',
                    qos: 1,
                    retain: false,
                ))->toArray(),
            ]
        );

        $thermalSchema = DeviceSchema::firstOrCreate([
            'device_type_id' => $thermalDeviceType->id,
            'name' => 'Thermal Sensor Contract',
        ]);

        $thermalVersion = DeviceSchemaVersion::firstOrCreate([
            'device_schema_id' => $thermalSchema->id,
            'version' => 1,
        ], [
            'status' => 'active',
            'notes' => 'Initial thermal sensor contract',
        ]);

        ParameterDefinition::firstOrCreate([
            'device_schema_version_id' => $thermalVersion->id,
            'key' => 'temp_c',
        ], [
            'label' => 'Temperature (°C)',
            'json_path' => '$.status.temp',
            'type' => ParameterDataType::Decimal,
            'unit' => 'Celsius',
            'required' => true,
            'is_critical' => true,
            'validation_rules' => ['min' => -40, 'max' => 85],
            'validation_error_code' => 'TEMP_RANGE',
            'mutation_expression' => [
                '+' => [
                    ['*' => [
                        ['var' => 'val'],
                        1.8,
                    ]],
                    32,
                ],
            ],
            'sequence' => 1,
            'is_active' => true,
        ]);

        ParameterDefinition::firstOrCreate([
            'device_schema_version_id' => $thermalVersion->id,
            'key' => 'humidity',
        ], [
            'label' => 'Humidity',
            'json_path' => 'status.humidity',
            'type' => ParameterDataType::Decimal,
            'unit' => 'Percent',
            'required' => true,
            'is_critical' => false,
            'validation_rules' => ['min' => 0, 'max' => 100],
            'validation_error_code' => 'HUMIDITY_RANGE',
            'sequence' => 2,
            'is_active' => true,
        ]);

        DerivedParameterDefinition::firstOrCreate([
            'device_schema_version_id' => $thermalVersion->id,
            'key' => 'heat_index',
        ], [
            'label' => 'Heat Index',
            'data_type' => ParameterDataType::Decimal,
            'unit' => 'Celsius',
            'expression' => [
                '+' => [
                    ['var' => 'temp_c'],
                    ['/' => [
                        ['var' => 'humidity'],
                        10,
                    ]],
                ],
            ],
            'dependencies' => ['temp_c', 'humidity'],
            'json_path' => 'computed.heat_index',
        ]);

        $networkSchema = DeviceSchema::firstOrCreate([
            'device_type_id' => $networkDeviceType->id,
            'name' => 'Network Gateway Contract',
        ]);

        $networkVersion = DeviceSchemaVersion::firstOrCreate([
            'device_schema_id' => $networkSchema->id,
            'version' => 1,
        ], [
            'status' => 'active',
            'notes' => 'Initial network telemetry contract',
        ]);

        ParameterDefinition::firstOrCreate([
            'device_schema_version_id' => $networkVersion->id,
            'key' => 'signal_strength',
        ], [
            'label' => 'Signal Strength',
            'json_path' => 'radio.signal',
            'type' => ParameterDataType::Integer,
            'unit' => 'dBm',
            'required' => true,
            'is_critical' => false,
            'validation_rules' => ['min' => -120, 'max' => 0],
            'validation_error_code' => 'SIGNAL_RANGE',
            'sequence' => 1,
            'is_active' => true,
        ]);

        ParameterDefinition::firstOrCreate([
            'device_schema_version_id' => $networkVersion->id,
            'key' => 'uptime_seconds',
        ], [
            'label' => 'Uptime (seconds)',
            'json_path' => '$.system.uptime',
            'type' => ParameterDataType::Integer,
            'unit' => 'Seconds',
            'required' => false,
            'is_critical' => false,
            'validation_rules' => ['min' => 0],
            'validation_error_code' => 'UPTIME_RANGE',
            'sequence' => 2,
            'is_active' => true,
        ]);

        $energySchema = DeviceSchema::firstOrCreate([
            'device_type_id' => $energyMeterType->id,
            'name' => 'Energy Meter Contract',
        ]);

        $energyVersion = DeviceSchemaVersion::firstOrCreate([
            'device_schema_id' => $energySchema->id,
            'version' => 1,
        ], [
            'status' => 'active',
            'notes' => 'Initial energy meter contract',
        ]);

        foreach (['V1', 'V2', 'V3'] as $index => $key) {
            ParameterDefinition::firstOrCreate([
                'device_schema_version_id' => $energyVersion->id,
                'key' => $key,
            ], [
                'label' => "Voltage {$key}",
                'json_path' => "voltages.{$key}",
                'type' => ParameterDataType::Decimal,
                'unit' => 'Volts',
                'required' => true,
                'is_critical' => true,
                'validation_rules' => ['min' => 0, 'max' => 480],
                'validation_error_code' => 'VOLTAGE_RANGE',
                'sequence' => $index + 1,
                'is_active' => true,
            ]);
        }

        foreach (['power_l1', 'power_l2', 'power_l3'] as $index => $key) {
            ParameterDefinition::firstOrCreate([
                'device_schema_version_id' => $energyVersion->id,
                'key' => $key,
            ], [
                'label' => "Power {$key}",
                'json_path' => "power.{$key}",
                'type' => ParameterDataType::Decimal,
                'unit' => 'Watts',
                'required' => true,
                'is_critical' => true,
                'validation_rules' => ['min' => 0],
                'validation_error_code' => 'POWER_RANGE',
                'sequence' => $index + 4,
                'is_active' => true,
            ]);
        }

        DerivedParameterDefinition::firstOrCreate([
            'device_schema_version_id' => $energyVersion->id,
            'key' => 'avg_voltage',
        ], [
            'label' => 'Average Voltage',
            'data_type' => ParameterDataType::Decimal,
            'unit' => 'Volts',
            'expression' => [
                '/' => [
                    ['+' => [
                        ['var' => 'V1'],
                        ['var' => 'V2'],
                        ['var' => 'V3'],
                    ]],
                    3,
                ],
            ],
            'dependencies' => ['V1', 'V2', 'V3'],
            'json_path' => 'computed.avg_voltage',
        ]);

        DerivedParameterDefinition::firstOrCreate([
            'device_schema_version_id' => $energyVersion->id,
            'key' => 'total_power',
        ], [
            'label' => 'Total Power',
            'data_type' => ParameterDataType::Decimal,
            'unit' => 'Watts',
            'expression' => [
                '+' => [
                    ['var' => 'power_l1'],
                    ['var' => 'power_l2'],
                    ['var' => 'power_l3'],
                ],
            ],
            'dependencies' => ['power_l1', 'power_l2', 'power_l3'],
            'json_path' => 'computed.total_power',
        ]);
    }
}
