<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Jobs\SimulateDevicePublishingJob;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\Models\TemporaryDevice;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Shared\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * @return array{
 *     organization: Organization,
 *     temporaryDevices: \Illuminate\Database\Eloquent\Collection<int, Device>,
 *     permanentDevices: \Illuminate\Database\Eloquent\Collection<int, Device>
 * }
 */
function createFleetSimulationFixture(int $temporaryDeviceCount = 2, int $permanentDeviceCount = 1): array
{
    $organization = Organization::factory()->create([
        'name' => 'Queued Fleet',
        'slug' => 'queued-fleet',
    ]);

    $deviceType = DeviceType::factory()->forOrganization($organization->id)->mqtt()->create();
    $schema = DeviceSchema::factory()->forDeviceType($deviceType)->create();
    $schemaVersion = DeviceSchemaVersion::factory()->active()->create([
        'device_schema_id' => $schema->id,
    ]);

    SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'telemetry',
        'suffix' => 'telemetry',
    ]);

    $temporaryDevices = Device::factory()->count($temporaryDeviceCount)->create([
        'organization_id' => $organization->id,
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $schemaVersion->id,
        'connection_state' => 'offline',
    ]);

    $temporaryDevices->each(function (Device $device): void {
        TemporaryDevice::factory()->for($device, 'device')->create();
    });

    $permanentDevices = Device::factory()->count($permanentDeviceCount)->create([
        'organization_id' => $organization->id,
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $schemaVersion->id,
        'connection_state' => 'offline',
    ]);

    return [
        'organization' => $organization,
        'temporaryDevices' => $temporaryDevices,
        'permanentDevices' => $permanentDevices,
    ];
}

it('queues only temporary devices by default and prints a preflight summary', function (): void {
    Queue::fake();

    $fixture = createFleetSimulationFixture();
    $organization = $fixture['organization'];
    $temporaryDevices = $fixture['temporaryDevices'];
    $permanentDevice = $fixture['permanentDevices']->sole();

    $this->artisan('iot:simulate-fleet', [
        'organization' => $organization->slug,
        '--count' => 3,
        '--interval' => 0,
    ])
        ->expectsOutput("Resolved organization: {$organization->name} (ID: {$organization->id}, slug: {$organization->slug})")
        ->expectsOutput('Scope: temporary-only')
        ->expectsOutput('Matched temporary devices: 2')
        ->expectsOutput('Matched total devices: 3')
        ->expectsOutput('Effective device count: 2')
        ->expectsOutput('Effective dispatch count: 6')
        ->expectsOutput('Simulation queue: redis-simulations/simulations')
        ->expectsOutput('Ingestion queue: redis/ingestion')
        ->expectsOutput('Queued 6 fleet simulation job(s).')
        ->assertSuccessful();

    Queue::assertPushedTimes(SimulateDevicePublishingJob::class, 6);
    Queue::assertPushed(SimulateDevicePublishingJob::class, function (SimulateDevicePublishingJob $job) use ($temporaryDevices): bool {
        return $job->count === 1
            && $job->intervalSeconds === 0
            && $job->schemaVersionTopicId === null
            && in_array($job->deviceId, $temporaryDevices->pluck('id')->all(), true);
    });
    Queue::assertNotPushed(SimulateDevicePublishingJob::class, fn (SimulateDevicePublishingJob $job): bool => $job->deviceId === $permanentDevice->id);
});

it('includes non-temporary devices when all is requested', function (): void {
    Queue::fake();

    $fixture = createFleetSimulationFixture();
    $organization = $fixture['organization'];
    $permanentDevice = $fixture['permanentDevices']->sole();

    $this->artisan('iot:simulate-fleet', [
        'organization' => $organization->id,
        '--all' => true,
        '--count' => 2,
        '--interval' => 0,
    ])
        ->expectsOutput("Resolved organization: {$organization->name} (ID: {$organization->id}, slug: {$organization->slug})")
        ->expectsOutput('Scope: all')
        ->expectsOutput('Matched temporary devices: 2')
        ->expectsOutput('Matched total devices: 3')
        ->expectsOutput('Effective device count: 3')
        ->expectsOutput('Effective dispatch count: 6')
        ->expectsOutput('Queued 6 fleet simulation job(s).')
        ->assertSuccessful();

    Queue::assertPushedTimes(SimulateDevicePublishingJob::class, 6);
    Queue::assertPushed(SimulateDevicePublishingJob::class, fn (SimulateDevicePublishingJob $job): bool => $job->deviceId === $permanentDevice->id);
});

it('warns when no temporary devices match and all is required to include permanent devices', function (): void {
    Queue::fake();

    $fixture = createFleetSimulationFixture(temporaryDeviceCount: 0, permanentDeviceCount: 2);
    $organization = $fixture['organization'];

    $this->artisan('iot:simulate-fleet', [
        'organization' => $organization->slug,
        '--count' => 2,
    ])
        ->expectsOutput("Resolved organization: {$organization->name} (ID: {$organization->id}, slug: {$organization->slug})")
        ->expectsOutput('Scope: temporary-only')
        ->expectsOutput('Matched temporary devices: 0')
        ->expectsOutput('Matched total devices: 2')
        ->expectsOutput('Effective device count: 0')
        ->expectsOutput('Effective dispatch count: 0')
        ->expectsOutput('No temporary devices matched this organization. Use --all to include non-temporary devices.')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('dispatches short delayed jobs for paced runs instead of long-running sleeping jobs', function (): void {
    Queue::fake();
    Carbon::setTestNow(Carbon::parse('2026-03-08 15:00:00'));

    $fixture = createFleetSimulationFixture(temporaryDeviceCount: 1, permanentDeviceCount: 0);
    $organization = $fixture['organization'];
    $device = $fixture['temporaryDevices']->sole();

    $this->artisan('iot:simulate-fleet', [
        'organization' => $organization->slug,
        '--count' => 3,
        '--interval' => 5,
    ])->assertSuccessful();

    Queue::assertPushedTimes(SimulateDevicePublishingJob::class, 3);
    Queue::assertPushed(SimulateDevicePublishingJob::class, function (SimulateDevicePublishingJob $job) use ($device): bool {
        return $job->deviceId === $device->id
            && $job->count === 1
            && $job->intervalSeconds === 0
            && $job->delay === null;
    });
    Queue::assertPushed(SimulateDevicePublishingJob::class, function (SimulateDevicePublishingJob $job) use ($device): bool {
        return $job->deviceId === $device->id
            && $job->delay instanceof \DateTimeInterface
            && Carbon::instance($job->delay)->equalTo(Carbon::parse('2026-03-08 15:00:05'));
    });
    Queue::assertPushed(SimulateDevicePublishingJob::class, function (SimulateDevicePublishingJob $job) use ($device): bool {
        return $job->deviceId === $device->id
            && $job->delay instanceof \DateTimeInterface
            && Carbon::instance($job->delay)->equalTo(Carbon::parse('2026-03-08 15:00:10'));
    });
});

it('configures dedicated fixed-process horizon supervisors for default ingestion and simulations', function (): void {
    expect(config('horizon.defaults.supervisor-default.queue'))->toBe(['default'])
        ->and(config('horizon.defaults.supervisor-default.processes'))->toBe(1)
        ->and(config('horizon.defaults.supervisor-ingestion.queue'))->toBe(['ingestion'])
        ->and(config('horizon.defaults.supervisor-ingestion.processes'))->toBe(1)
        ->and(config('horizon.defaults.supervisor-side-effects.queue'))->toBe(['telemetry-side-effects'])
        ->and(config('horizon.defaults.supervisor-side-effects.processes'))->toBe(1)
        ->and(config('horizon.defaults.supervisor-simulations.queue'))->toBe(['simulations'])
        ->and(config('horizon.defaults.supervisor-simulations.processes'))->toBe(1)
        ->and(config('horizon.defaults.supervisor-simulations.timeout'))->toBe(240)
        ->and(config('horizon.environments.local.supervisor-ingestion.processes'))->toBe(4)
        ->and(config('horizon.environments.local.supervisor-side-effects.processes'))->toBe(4)
        ->and(config('horizon.environments.local.supervisor-simulations.processes'))->toBe(4)
        ->and(config('horizon.environments.production.supervisor-ingestion.processes'))->toBe(8)
        ->and(config('horizon.environments.production.supervisor-side-effects.processes'))->toBe(8)
        ->and(config('horizon.environments.production.supervisor-simulations.processes'))->toBe(8)
        ->and(config('queue.connections.redis-simulations.retry_after'))->toBe(300);
});
