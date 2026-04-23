<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Shared\Models\Entity;
use App\Domain\Shared\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Import hierarchical entities from the legacy project located at ../iot-demo.
 *
 * Behavior:
 * - If a JSON export exists at ../iot-demo/sections_export.json it will be used.
 * - Otherwise the seeder will attempt to read database connection details from
 *   ../iot-demo/.env and connect to that database to read `sections` and
 *   (optionally) `section_types` for icons.
 */
class ImportLegacyEntitiesSeeder extends Seeder
{
    private const CSV_HEADERS = [
        'legacy_id',
        'name',
        'parent_id',
        'organization_id',
        'organization_name',
        'icon',
        'uuid',
    ];

    /**
     * Import the current supported legacy organizations only.
     *
     * We intentionally keep this tight to avoid obsolete tenants and mixed data.
     * SriLankan is explicitly excluded per product requirement.
     *
     * @var list<string>
     */
    private const TARGET_LEGACY_ORGANIZATION_NAMES = [
        'Teejay',
    ];

    /**
     * @var list<string>
     */
    private const EXCLUDED_LEGACY_ORGANIZATION_NAMES = [
        'SriLankan Airlines Limited',
        'Keells',
    ];

    public function run(): void
    {
        $rows = $this->loadRows();

        if (empty($rows)) {
            $this->command->info('No legacy sections found to import.');

            return;
        }

        $rows = $this->filterRows($rows);

        if ($rows === []) {
            $this->writeCsv([]);
            $this->command->info('No eligible legacy entities remained after organization filtering.');

            return;
        }

        $this->command->info('Importing '.count($rows).' legacy sections (after organization filtering)...');

        $idMap = [];
        $organizationIdsByNormalizedName = $this->localOrganizationIdsByNormalizedName();
        $skippedRows = 0;

        $this->writeCsv($rows);

        foreach ($rows as $row) {
            $legacyId = $row['id'] ?? null;
            $uuid = $row['uuid'] ?? null;
            $organizationId = $this->resolveLocalOrganizationId($row, $organizationIdsByNormalizedName);

            if ($organizationId === null) {
                $skippedRows++;

                continue;
            }

            if ($uuid) {
                $existing = Entity::query()->where('uuid', $uuid)->first();
                if ($existing) {
                    $existing->update([
                        'organization_id' => $organizationId,
                        'name' => $row['name'] ?? $row['label'] ?? $existing->name,
                        'icon' => $row['icon'] ?? $existing->icon,
                    ]);

                    if ($legacyId !== null) {
                        $idMap[(int) $legacyId] = $existing->id;
                    }

                    continue;
                }
            }

            $new = Entity::create([
                'organization_id' => $organizationId,
                'name' => $row['name'] ?? $row['label'] ?? 'Unnamed',
                'icon' => $row['icon'] ?? null,
                'parent_id' => null,
                'uuid' => $uuid ?? null,
            ]);

            if ($legacyId !== null) {
                $idMap[(int) $legacyId] = $new->id;
            }
        }

        foreach ($rows as $row) {
            $legacyRowId = isset($row['id']) ? (int) $row['id'] : null;
            $legacyParentId = isset($row['parent_id']) && $row['parent_id'] !== '' ? (int) $row['parent_id'] : null;

            if ($legacyRowId !== null && $legacyParentId !== null && isset($idMap[$legacyRowId], $idMap[$legacyParentId])) {
                $targetId = $idMap[$legacyRowId];
                $parentId = $idMap[$legacyParentId];

                $ent = Entity::query()->find($targetId);
                if ($ent && $ent->parent_id !== $parentId) {
                    $ent->parent_id = $parentId;
                    $ent->save();
                }
            }
        }

        // Refresh labels for all imported records
        foreach (Entity::whereIn('id', array_values($idMap))->get() as $entity) {
            $entity->save();
        }

        if ($skippedRows > 0) {
            $this->command->warn("Skipped {$skippedRows} rows because no matching local organization was found.");
        }

        $this->command->info('Import complete.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadRows(): array
    {
        $csvRows = $this->loadRowsFromCsv();

        if ($csvRows !== []) {
            return $csvRows;
        }

        $databaseRows = $this->loadRowsFromLegacyDatabase();

        if ($databaseRows !== []) {
            return $databaseRows;
        }

        return $this->loadRowsFromJsonExports();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadRowsFromCsv(): array
    {
        $csvImportPath = database_path('seeders/exports/teejay_entities.csv');

        if (! File::exists($csvImportPath) || filesize($csvImportPath) === 0) {
            return [];
        }

        $this->command->info("Found prepared CSV import: {$csvImportPath} — parsing rows");

        $rows = [];
        $fp = fopen($csvImportPath, 'r');

        if ($fp === false) {
            return [];
        }

        $header = fgetcsv($fp) ?: [];

        if (! in_array('organization_name', $header, true)) {
            fclose($fp);
            $this->command->warn('Prepared CSV is missing organization_name; refreshing from legacy source.');

            return [];
        }

        while (($data = fgetcsv($fp)) !== false) {
            $assoc = array_combine($header, $data) ?: [];
            $rows[] = [
                'id' => $assoc['legacy_id'] ?: null,
                'name' => $assoc['name'] ?: null,
                'parent_id' => $assoc['parent_id'] ?: null,
                'organization_id' => $assoc['organization_id'] ?: null,
                'organization_name' => $assoc['organization_name'] ?: null,
                'icon' => $assoc['icon'] ?: null,
                'uuid' => $assoc['uuid'] ?: null,
            ];
        }

        fclose($fp);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadRowsFromLegacyDatabase(): array
    {
        $envPath = base_path('../iot-demo/.env');

        if (! File::exists($envPath)) {
            return [];
        }

        $env = File::lines($envPath)->mapWithKeys(function ($line) {
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);

                return [trim($key) => trim($value)];
            }

            return [];
        })->toArray();

        $database = $env['DB_DATABASE'] ?? null;

        if (! $database) {
            return [];
        }

        $connectionName = 'legacy_import';
        config(["database.connections.{$connectionName}" => [
            'driver' => $env['DB_CONNECTION'] ?? 'mysql',
            'host' => $env['DB_HOST'] ?? '127.0.0.1',
            'port' => $env['DB_PORT'] ?? '3306',
            'database' => $database,
            'username' => $env['DB_USERNAME'] ?? '',
            'password' => $env['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]]);

        $this->command->info('Querying legacy database for sections with organization names...');

        return DB::connection($connectionName)->table('sections')
            ->join('organizations', 'sections.organization_id', '=', 'organizations.id')
            ->leftJoin('section_types', 'sections.section_type_id', '=', 'section_types.id')
            ->select('sections.*', 'section_types.icon', 'organizations.name as organization_name')
            ->orderBy('sections.id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadRowsFromJsonExports(): array
    {
        $exportPaths = [
            base_path('../iot-demo/sections_export.json'),
            base_path('../iot-demo/storage/sections.json'),
            base_path('../iot-demo/sections.json'),
        ];

        foreach ($exportPaths as $path) {
            if (! File::exists($path)) {
                continue;
            }

            $this->command->info("Importing legacy entities from JSON: {$path}");
            $rows = json_decode(File::get($path), true);

            if (is_array($rows)) {
                return $rows;
            }
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function filterRows(array $rows): array
    {
        $allowedOrganizations = array_map([$this, 'normalizeName'], self::TARGET_LEGACY_ORGANIZATION_NAMES);
        $excludedOrganizations = array_map([$this, 'normalizeName'], self::EXCLUDED_LEGACY_ORGANIZATION_NAMES);

        return array_values(array_filter($rows, function (array $row) use ($allowedOrganizations, $excludedOrganizations): bool {
            $organizationName = $this->normalizeName($row['organization_name'] ?? null);

            if ($organizationName === '') {
                return false;
            }

            if (in_array($organizationName, $excludedOrganizations, true)) {
                return false;
            }

            return in_array($organizationName, $allowedOrganizations, true);
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeCsv(array $rows): void
    {
        $exportDir = database_path('seeders/exports');

        if (! file_exists($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $csvPath = $exportDir.'/teejay_entities.csv';
        $fp = fopen($csvPath, 'w');

        if ($fp === false) {
            return;
        }

        fputcsv($fp, self::CSV_HEADERS);

        foreach ($rows as $row) {
            fputcsv($fp, [
                $row['id'] ?? null,
                $row['name'] ?? $row['label'] ?? null,
                $row['parent_id'] ?? null,
                $row['organization_id'] ?? null,
                $row['organization_name'] ?? null,
                $row['icon'] ?? null,
                $row['uuid'] ?? null,
            ]);
        }

        fclose($fp);

        $this->command->info("Wrote import CSV to: {$csvPath}");
    }

    /**
     * @return array<string, int>
     */
    private function localOrganizationIdsByNormalizedName(): array
    {
        return Organization::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Organization $organization): array => [
                $this->normalizeName($organization->name) => $organization->id,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $organizationIdsByNormalizedName
     */
    private function resolveLocalOrganizationId(array $row, array $organizationIdsByNormalizedName): ?int
    {
        $normalizedName = $this->normalizeName($row['organization_name'] ?? null);

        return $organizationIdsByNormalizedName[$normalizedName] ?? null;
    }

    private function normalizeName(?string $name): string
    {
        return strtolower(trim((string) $name));
    }
}
