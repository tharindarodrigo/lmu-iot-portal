<?php

declare(strict_types=1);

namespace App\Console\Commands\IoT;

use App\Domain\DeviceManagement\Jobs\SimulateDevicePublishingJob;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\Shared\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class SimulateFleetCommand extends Command
{
    protected $signature = 'iot:simulate-fleet
                            {organization : Organization ID or slug}
                            {--all : Include non-temporary devices in the selected organization}
                            {--devices=0 : Limit the number of devices to queue (0 = all)}
                            {--count=1 : Number of publish iterations per device}
                            {--interval=0 : Seconds between publishes for each queued device}
                            {--topic-id= : Restrict simulation to a single publish topic ID}';

    protected $description = 'Queue telemetry simulation jobs for an organization fleet, targeting temporary devices by default';

    public function handle(): int
    {
        $organization = $this->resolveOrganization((string) $this->argument('organization'));

        if (! $organization instanceof Organization) {
            $this->error('Organization not found.');

            return self::FAILURE;
        }

        $deviceLimit = max(0, (int) $this->option('devices'));
        $publishCount = max(1, (int) $this->option('count'));
        $intervalSeconds = max(0, (int) $this->option('interval'));
        $simulateAllDevices = (bool) $this->option('all');
        $topicOption = $this->option('topic-id');
        $schemaVersionTopicId = is_numeric($topicOption) ? (int) $topicOption : null;

        $publishCapableDevicesQuery = $this->publishCapableDevicesQuery(
            organization: $organization,
            schemaVersionTopicId: $schemaVersionTopicId,
        );

        $matchedTotalDevices = (clone $publishCapableDevicesQuery)->count();
        $matchedTemporaryDevices = (clone $publishCapableDevicesQuery)
            ->whereHas('temporaryDevice')
            ->count();

        $scopedDevicesQuery = $simulateAllDevices
            ? clone $publishCapableDevicesQuery
            : (clone $publishCapableDevicesQuery)->whereHas('temporaryDevice');

        $matchedScopedDevices = (clone $scopedDevicesQuery)->count();
        $effectiveDeviceCount = $deviceLimit > 0
            ? min($deviceLimit, $matchedScopedDevices)
            : $matchedScopedDevices;
        $effectiveDispatchCount = $effectiveDeviceCount * $publishCount;

        $this->displayPreflight(
            organization: $organization,
            simulateAllDevices: $simulateAllDevices,
            matchedTemporaryDevices: $matchedTemporaryDevices,
            matchedTotalDevices: $matchedTotalDevices,
            effectiveDeviceCount: $effectiveDeviceCount,
            effectiveDispatchCount: $effectiveDispatchCount,
        );

        if ($matchedScopedDevices === 0) {
            if (! $simulateAllDevices && $matchedTotalDevices > 0) {
                $this->warn('No temporary devices matched this organization. Use --all to include non-temporary devices.');
            } else {
                $this->warn('No devices matched the requested fleet simulation scope.');
            }

            return self::SUCCESS;
        }

        $queuedJobs = 0;
        $remainingDevices = $effectiveDeviceCount;

        $scopedDevicesQuery
            ->select('devices.id')
            ->orderBy('devices.id')
            ->chunkById(500, function ($devices) use (&$queuedJobs, &$remainingDevices, $publishCount, $intervalSeconds, $schemaVersionTopicId): bool|null {
                foreach ($devices as $device) {
                    if ($remainingDevices <= 0) {
                        return false;
                    }

                    $queuedJobs += SimulateDevicePublishingJob::dispatchIterations(
                        deviceId: $device->id,
                        count: $publishCount,
                        intervalSeconds: $intervalSeconds,
                        schemaVersionTopicId: $schemaVersionTopicId,
                    );

                    $remainingDevices--;
                }

                return null;
            });

        $this->info("Queued {$queuedJobs} fleet simulation job(s).");

        return self::SUCCESS;
    }

    /**
     * @return Builder<Device>
     */
    private function publishCapableDevicesQuery(Organization $organization, ?int $schemaVersionTopicId): Builder
    {
        return Device::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('device_schema_version_id')
            ->whereHas('schemaVersion.topics', function (Builder $query) use ($schemaVersionTopicId): void {
                $query->where('direction', TopicDirection::Publish->value);

                if ($schemaVersionTopicId !== null) {
                    $query->whereKey($schemaVersionTopicId);
                }
            });
    }

    private function displayPreflight(
        Organization $organization,
        bool $simulateAllDevices,
        int $matchedTemporaryDevices,
        int $matchedTotalDevices,
        int $effectiveDeviceCount,
        int $effectiveDispatchCount,
    ): void {
        $organizationSlug = trim((string) $organization->slug);
        $simulationQueue = $this->resolveStringConfig('queue.connections.redis-simulations.queue', 'simulations');
        $ingestionQueueConnection = $this->resolveStringConfig('ingestion.queue_connection', $this->resolveStringConfig('queue.default', 'database'));
        $ingestionQueue = $this->resolveStringConfig('ingestion.queue', 'ingestion');

        if ($organizationSlug === '') {
            $organizationSlug = '-';
        }

        $this->line("Resolved organization: {$organization->name} (ID: {$organization->id}, slug: {$organizationSlug})");
        $this->line('Scope: '.($simulateAllDevices ? 'all' : 'temporary-only'));
        $this->line("Matched temporary devices: {$matchedTemporaryDevices}");
        $this->line("Matched total devices: {$matchedTotalDevices}");
        $this->line("Effective device count: {$effectiveDeviceCount}");
        $this->line("Effective dispatch count: {$effectiveDispatchCount}");
        $this->line("Simulation queue: redis-simulations/{$simulationQueue}");
        $this->line("Ingestion queue: {$ingestionQueueConnection}/{$ingestionQueue}");
    }

    private function resolveStringConfig(string $key, string $fallback): string
    {
        $value = config($key, $fallback);

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : $fallback;
    }

    private function resolveOrganization(string $organizationArgument): ?Organization
    {
        if (ctype_digit($organizationArgument)) {
            return Organization::query()->find((int) $organizationArgument);
        }

        return Organization::query()->where('slug', $organizationArgument)->first();
    }
}
