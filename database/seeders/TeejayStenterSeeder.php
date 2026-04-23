<?php

declare(strict_types=1);

namespace Database\Seeders;

class TeejayStenterSeeder extends TeejayMigrationSeederSupport
{
    private const DEVICE_TYPE_KEY = 'stenter_line';

    private const DEVICE_TYPE_NAME = 'Stenter Line';

    private const BASE_TOPIC = 'lines/stenter';

    private const SCHEMA_NAME = 'Stenter Line Contract';

    public function run(): void
    {
        $organization = $this->ensureOrganization();

        $schemaVersion = $this->upsertSchemaVersion(
            deviceTypeKey: self::DEVICE_TYPE_KEY,
            deviceTypeName: self::DEVICE_TYPE_NAME,
            baseTopic: self::BASE_TOPIC,
            schemaName: self::SCHEMA_NAME,
            parameters: [],
            status: 'draft',
            notes: 'Recovered Teejay stenter line aggregate inventory with linked component metadata.',
        );

        $expectedExternalIds = [];

        foreach (TeejayMigrationInventory::stenters() as $stenterConfig) {
            $device = $this->upsertStandaloneDevice(
                organization: $organization,
                schemaVersion: $schemaVersion,
                externalId: $stenterConfig['external_id'],
                name: $stenterConfig['name'],
                metadata: [
                    'migration_origin' => TeejayMigrationSeeder::ORGANIZATION_SLUG,
                    'migration_role' => 'aggregate_device',
                    'migration_device_type' => 'Stenter',
                    'schema_variant' => 'stenter-line',
                    'components' => $stenterConfig['components'],
                ],
            );

            $expectedExternalIds[] = $device->external_id;
        }

        $this->cleanupDevices($organization, 'Stenter', $expectedExternalIds);
        $this->cleanupUnusedDraftSchemaVersions(self::DEVICE_TYPE_KEY, self::SCHEMA_NAME, [$schemaVersion->version]);
    }
}
