<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\Reporting\Actions\CreateReportRunAction;
use App\Domain\Reporting\Actions\DeleteReportRunAction;
use App\Domain\Reporting\Actions\DownloadReportRunAction;
use App\Domain\Reporting\Actions\UpdateOrganizationReportSettingsAction;
use App\Domain\Reporting\Enums\ReportRunStatus;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Jobs\GenerateReportRunJob;
use App\Domain\Reporting\Models\OrganizationReportSetting;
use App\Domain\Reporting\Models\ReportRun;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('reporting.storage_disk', 'local');
});

function captureReportingDownloadBody(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    return is_string($content) ? $content : '';
}

it('creates a report run through the DDD action without calling the internal API', function (): void {
    Queue::fake();
    Http::fake();

    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $user->organizations()->attach($organization->id);
    $device = Device::factory()->create(['organization_id' => $organization->id]);

    $resolved = app(CreateReportRunAction::class)($user, [
        'organization_id' => $organization->id,
        'device_id' => $device->id,
        'type' => ReportType::ParameterValues->value,
        'from_at' => now()->subDay()->toIso8601String(),
        'until_at' => now()->toIso8601String(),
        'timezone' => 'UTC',
    ]);

    expect($resolved->organization_id)->toBe($organization->id)
        ->and($resolved->device_id)->toBe($device->id)
        ->and($resolved->requested_by_user_id)->toBe($user->id)
        ->and($resolved->type)->toBe(ReportType::ParameterValues)
        ->and($resolved->status)->toBe(ReportRunStatus::Queued);

    $this->assertDatabaseHas('report_runs', [
        'id' => $resolved->id,
        'organization_id' => $organization->id,
        'device_id' => $device->id,
        'requested_by_user_id' => $user->id,
        'type' => ReportType::ParameterValues->value,
        'status' => ReportRunStatus::Queued->value,
    ]);

    Queue::assertPushed(GenerateReportRunJob::class, fn (GenerateReportRunJob $job): bool => $job->reportRunId === $resolved->id);
    Http::assertNothingSent();
});

it('updates organization report settings through the DDD action without calling the internal API', function (): void {
    Http::fake();

    $organization = Organization::factory()->create();

    $resolved = app(UpdateOrganizationReportSettingsAction::class)([
        'organization_id' => $organization->id,
        'timezone' => 'Asia/Colombo',
        'max_range_days' => 31,
        'shift_schedules' => [
            [
                'name' => 'Day / Night',
                'windows' => [
                    ['name' => 'Day', 'start' => '06:00', 'end' => '18:00'],
                    ['name' => 'Night', 'start' => '18:00', 'end' => '06:00'],
                ],
            ],
        ],
    ]);

    expect($resolved)->toBeInstanceOf(OrganizationReportSetting::class)
        ->and($resolved->organization_id)->toBe($organization->id)
        ->and($resolved->timezone)->toBe('Asia/Colombo')
        ->and($resolved->max_range_days)->toBe(31)
        ->and(data_get($resolved->shift_schedules, '0.name'))->toBe('Day / Night');

    $this->assertDatabaseHas('organization_report_settings', [
        'organization_id' => $organization->id,
        'timezone' => 'Asia/Colombo',
        'max_range_days' => 31,
    ]);

    Http::assertNothingSent();
});

it('enforces max range validation when creating report runs directly', function (): void {
    Queue::fake();

    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $user->organizations()->attach($organization->id);
    $device = Device::factory()->create(['organization_id' => $organization->id]);

    OrganizationReportSetting::factory()->create([
        'organization_id' => $organization->id,
        'max_range_days' => 1,
    ]);

    expect(fn (): ReportRun => app(CreateReportRunAction::class)($user, [
        'organization_id' => $organization->id,
        'device_id' => $device->id,
        'type' => ReportType::ParameterValues->value,
        'from_at' => now()->subDays(3)->toIso8601String(),
        'until_at' => now()->toIso8601String(),
        'timezone' => 'UTC',
    ]))
        ->toThrow(ValidationException::class, 'The selected range exceeds the maximum allowed period of 1 days.');

    Queue::assertNothingPushed();
});

it('enforces shift schedule validation when updating settings directly', function (): void {
    $organization = Organization::factory()->create();

    expect(fn (): OrganizationReportSetting => app(UpdateOrganizationReportSettingsAction::class)([
        'organization_id' => $organization->id,
        'timezone' => 'UTC',
        'max_range_days' => 31,
        'shift_schedules' => [
            [
                'name' => 'Overlapping',
                'windows' => [
                    ['name' => 'A', 'start' => '06:00', 'end' => '14:00'],
                    ['name' => 'B', 'start' => '13:00', 'end' => '22:00'],
                    ['name' => 'C', 'start' => '22:00', 'end' => '06:00'],
                ],
            ],
        ],
    ]))
        ->toThrow(ValidationException::class, 'Shift schedule windows must be ordered without overlaps.');
});

it('deletes a report run through the DDD action without calling the internal API', function (): void {
    Storage::fake('local');
    Http::fake();

    $organization = Organization::factory()->create();
    $device = Device::factory()->create(['organization_id' => $organization->id]);
    $user = User::factory()->create();
    Storage::disk('local')->put('reports/sample.csv', "a,b\n1,2\n");

    $reportRun = ReportRun::query()->create([
        'organization_id' => $organization->id,
        'device_id' => $device->id,
        'requested_by_user_id' => $user->id,
        'type' => ReportType::ParameterValues,
        'status' => ReportRunStatus::Queued,
        'format' => 'csv',
        'from_at' => now()->subDay(),
        'until_at' => now(),
        'timezone' => 'UTC',
        'storage_disk' => 'local',
        'storage_path' => 'reports/sample.csv',
        'file_name' => 'sample.csv',
    ]);

    app(DeleteReportRunAction::class)($reportRun);

    expect(ReportRun::query()->find($reportRun->id))->toBeNull()
        ->and(Storage::disk('local')->exists('reports/sample.csv'))->toBeFalse();

    Http::assertNothingSent();
});

it('downloads a report through the DDD action without calling the internal API', function (): void {
    Storage::fake('local');
    Http::fake();

    $organization = Organization::factory()->create();
    $device = Device::factory()->create(['organization_id' => $organization->id]);
    $user = User::factory()->create();
    Storage::disk('local')->put('reports/sample.csv', "a,b\n1,2\n");

    $reportRun = ReportRun::query()->create([
        'organization_id' => $organization->id,
        'device_id' => $device->id,
        'requested_by_user_id' => $user->id,
        'type' => ReportType::ParameterValues,
        'status' => ReportRunStatus::Completed,
        'format' => 'csv',
        'from_at' => now()->subDay(),
        'until_at' => now(),
        'timezone' => 'UTC',
        'storage_disk' => 'local',
        'storage_path' => 'reports/sample.csv',
        'file_name' => 'sample.csv',
        'file_size' => 12,
    ]);

    $response = app(DownloadReportRunAction::class)($reportRun);
    $body = captureReportingDownloadBody($response);

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and((string) $response->headers->get('content-type'))->toContain('text/csv')
        ->and((string) $response->headers->get('content-disposition'))->toContain('sample.csv')
        ->and($body)->toContain("a,b\n1,2");

    Http::assertNothingSent();
});
