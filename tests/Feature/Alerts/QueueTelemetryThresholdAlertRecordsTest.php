<?php

declare(strict_types=1);

use App\Domain\Alerts\Listeners\QueueTelemetryThresholdAlertRecords;
use App\Domain\Alerts\Models\Alert;
use App\Domain\Automation\Models\AutomationNotificationProfile;
use App\Domain\Automation\Models\AutomationThresholdPolicy;
use App\Domain\Automation\Services\ThresholdPolicyWorkflowProjector;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Enums\MetricUnit;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Shared\Models\Organization;
use App\Domain\Telemetry\Enums\ValidationStatus;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use App\Events\TelemetryReceived;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('opens and normalizes dashboard alerts for threshold policies without notification profiles', function (): void {
    $fixture = createRecordOnlyThresholdAlertFixture();
    $listener = app(QueueTelemetryThresholdAlertRecords::class);

    $breachTelemetryLog = createRecordOnlyThresholdTelemetryLog($fixture, 9.4, Carbon::parse('2026-03-26 12:00:00'));
    $listener->handle(new TelemetryReceived($breachTelemetryLog));

    $alert = Alert::query()
        ->where('threshold_policy_id', $fixture['policy']->id)
        ->whereNull('normalized_at')
        ->first();

    expect($alert)->not->toBeNull()
        ->and($alert?->alerted_at?->toIso8601String())->toBe($breachTelemetryLog->recorded_at->toIso8601String())
        ->and($alert?->alert_notification_sent_at)->toBeNull();

    $normalTelemetryLog = createRecordOnlyThresholdTelemetryLog($fixture, 4.2, Carbon::parse('2026-03-26 12:05:00'));
    $listener->handle(new TelemetryReceived($normalTelemetryLog));

    $alert?->refresh();

    expect($alert)->not->toBeNull()
        ->and($alert?->normalized_at?->toIso8601String())->toBe($normalTelemetryLog->recorded_at->toIso8601String())
        ->and($alert?->normalized_notification_sent_at)->toBeNull();
});

it('keeps recording dashboard alerts when a managed workflow exists but is paused', function (): void {
    $fixture = createRecordOnlyThresholdAlertFixture(withNotificationProfile: true);
    $policy = $fixture['policy'];

    app(ThresholdPolicyWorkflowProjector::class)->sync($policy);

    $policy->forceFill([
        'notification_profile_id' => null,
    ])->saveQuietly();

    app(ThresholdPolicyWorkflowProjector::class)->sync($policy->fresh());

    $policy = $policy->fresh(['managedWorkflow']);

    expect($policy?->managedWorkflow?->status?->value)->toBe('paused');

    $breachTelemetryLog = createRecordOnlyThresholdTelemetryLog($fixture, 9.1, Carbon::parse('2026-03-26 13:00:00'));

    app(QueueTelemetryThresholdAlertRecords::class)->handle(new TelemetryReceived($breachTelemetryLog));

    expect(Alert::query()
        ->where('threshold_policy_id', $policy->id)
        ->whereNull('normalized_at')
        ->exists())->toBeTrue();
});

/**
 * @return array{
 *     organization: Organization,
 *     device: Device,
 *     topic: SchemaVersionTopic,
 *     parameter: ParameterDefinition,
 *     policy: AutomationThresholdPolicy
 * }
 */
function createRecordOnlyThresholdAlertFixture(bool $withNotificationProfile = false): array
{
    $organization = Organization::factory()->create();
    $deviceType = DeviceType::factory()->forOrganization($organization->id)->mqtt()->create();
    $schema = DeviceSchema::factory()->forDeviceType($deviceType)->create();
    $schemaVersion = DeviceSchemaVersion::factory()->active()->create([
        'device_schema_id' => $schema->id,
    ]);
    $topic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'telemetry',
        'suffix' => 'telemetry',
    ]);
    $parameter = ParameterDefinition::factory()->create([
        'schema_version_topic_id' => $topic->id,
        'key' => 'temperature',
        'label' => 'Temperature',
        'json_path' => '$.temperature',
        'type' => ParameterDataType::Decimal,
        'unit' => MetricUnit::Celsius->value,
        'required' => true,
        'is_active' => true,
    ]);
    $device = Device::factory()->create([
        'organization_id' => $organization->id,
        'device_type_id' => $deviceType->id,
        'device_schema_version_id' => $schemaVersion->id,
        'name' => 'CLD 07-02',
        'connection_state' => 'online',
        'last_seen_at' => now(),
    ]);
    $profile = $withNotificationProfile
        ? AutomationNotificationProfile::factory()->sms()->create([
            'organization_id' => $organization->id,
            'body' => 'Temperature breach',
        ])
        : null;

    $policy = AutomationThresholdPolicy::factory()
        ->when($profile === null, fn ($factory) => $factory->withoutNotificationProfile())
        ->create([
            'organization_id' => $organization->id,
            'device_id' => $device->id,
            'parameter_definition_id' => $parameter->id,
            'notification_profile_id' => $profile?->id,
            'name' => 'CLD 07-02 Temperature Threshold',
            'minimum_value' => 2,
            'maximum_value' => 8,
            'is_active' => true,
        ]);

    return [
        'organization' => $organization,
        'device' => $device,
        'topic' => $topic,
        'parameter' => $parameter,
        'policy' => $policy,
    ];
}

/**
 * @param  array{
 *     device: Device,
 *     topic: SchemaVersionTopic
 * }  $fixture
 */
function createRecordOnlyThresholdTelemetryLog(array $fixture, float $value, Carbon $recordedAt): DeviceTelemetryLog
{
    return DeviceTelemetryLog::factory()
        ->forDevice($fixture['device'])
        ->forTopic($fixture['topic'])
        ->create([
            'transformed_values' => [
                'temperature' => $value,
            ],
            'raw_payload' => [
                'temperature' => $value,
            ],
            'recorded_at' => $recordedAt,
            'received_at' => $recordedAt,
            'validation_status' => ValidationStatus::Valid,
        ]);
}
