<?php

declare(strict_types=1);

namespace App\Console\Commands\Automation;

use App\Domain\Automation\Services\SriLankanThresholdPolicyImporter;
use Database\Seeders\SriLankanMigrationSeeder;
use Illuminate\Console\Command;
use Throwable;

class ImportSriLankanThresholdPoliciesCommand extends Command
{
    protected $signature = 'automation:import-sri-lankan-threshold-policies
        {--organization='.SriLankanMigrationSeeder::ORGANIZATION_SLUG.' : Organization slug to import into}';

    protected $description = 'Import SriLankan legacy alert rules into automation threshold policies and notification profiles.';

    public function __construct(
        private readonly SriLankanThresholdPolicyImporter $importer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $organizationSlug = (string) $this->option('organization');

        try {
            $summary = $this->importer->import($organizationSlug);
        } catch (Throwable $throwable) {
            $this->components->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->components->info('SriLankan threshold policy import completed.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Profiles created', (string) $summary['profiles_created']],
                ['Profiles updated', (string) $summary['profiles_updated']],
                ['Policies created', (string) $summary['policies_created']],
                ['Policies updated', (string) $summary['policies_updated']],
                ['Workflows synced', (string) $summary['workflows_synced']],
            ],
        );

        foreach ($summary['skipped'] as $message) {
            $this->components->warn($message);
        }

        return self::SUCCESS;
    }
}
