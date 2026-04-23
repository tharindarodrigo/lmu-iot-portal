<?php

declare(strict_types=1);

namespace Database\Seeders;

class TeejayHubsSeeder extends TeejayMigrationSeederSupport
{
    public function run(): void
    {
        $organization = $this->ensureOrganization();

        $this->ensureHubs($organization);
        $this->cleanupHubs($organization);
    }
}
