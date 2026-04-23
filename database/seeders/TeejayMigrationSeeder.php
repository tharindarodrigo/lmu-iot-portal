<?php

declare(strict_types=1);

namespace Database\Seeders;

class TeejayMigrationSeeder extends TeejayMigrationSeederSupport
{
    public const ORGANIZATION_SLUG = 'teejay';

    public const ORGANIZATION_NAME = 'Teejay';

    public function run(): void
    {
        $this->ensureOrganization();

        $this->call([
            TeejayHubsSeeder::class,
            TeejayAcEnergyMateSeeder::class,
            TeejayWaterFlowVolumeSeeder::class,
            TeejayModbusLevelSensorSeeder::class,
            TeejayFabricLengthSeeder::class,
            TeejayFabricLengthShortSeeder::class,
            TeejayStatusSeeder::class,
            TeejayTemperatureSeeder::class,
            TeejaySteamMeterSeeder::class,
            TeejayPressureSeeder::class,
            TeejayStenterSeeder::class,
        ]);
    }
}
