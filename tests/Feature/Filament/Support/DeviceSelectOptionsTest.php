<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\Shared\Models\Organization;
use App\Support\DeviceSelectOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('groups device options by device type and resolves nested labels', function (): void {
    $organization = Organization::factory()->create();
    $energyMeterType = DeviceType::factory()->create([
        'name' => 'Energy Meter',
        'key' => 'energy_meter_select_options',
    ]);
    $steamMeterType = DeviceType::factory()->create([
        'name' => 'Steam Meter',
        'key' => 'steam_meter_select_options',
    ]);

    $energyMeter = Device::factory()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $energyMeterType->id,
        'name' => 'Main Compressor Meter',
        'external_id' => 'EM-100',
    ]);
    $steamMeter = Device::factory()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $steamMeterType->id,
        'name' => 'Steam Header',
        'external_id' => 'SM-200',
    ]);

    $options = DeviceSelectOptions::groupedByType(
        Device::query()->where('organization_id', $organization->id),
    );

    expect($options)
        ->toHaveKey('Energy Meter')
        ->toHaveKey('Steam Meter')
        ->and($options['Energy Meter'])
        ->toMatchArray([$energyMeter->id => 'Main Compressor Meter (EM-100)'])
        ->and($options['Steam Meter'])
        ->toMatchArray([$steamMeter->id => 'Steam Header (SM-200)'])
        ->and(DeviceSelectOptions::findLabel($options, $steamMeter->id))
        ->toBe('Steam Header (SM-200)');
});

it('can collapse a single device type group into flat options and use uuid fallback labels', function (): void {
    $organization = Organization::factory()->create();
    $statusType = DeviceType::factory()->create([
        'name' => 'Status Sensor',
        'key' => 'status_sensor_select_options',
    ]);

    $statusDevice = Device::factory()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $statusType->id,
        'name' => 'Line Status Sensor',
        'external_id' => null,
    ]);

    $options = DeviceSelectOptions::groupedByType(
        Device::query()->where('organization_id', $organization->id),
        useUuidFallback: true,
        collapseSingleGroup: true,
    );

    expect($options)
        ->toMatchArray([
            $statusDevice->id => "Line Status Sensor ({$statusDevice->uuid})",
        ])
        ->and(DeviceSelectOptions::findLabel($options, $statusDevice->id))
        ->toBe("Line Status Sensor ({$statusDevice->uuid})");
});
