<?php

declare(strict_types=1);

use App\Domain\Alerts\Models\Alert;
use App\Domain\Automation\Models\AutomationNotificationProfile;
use App\Domain\Automation\Models\AutomationThresholdPolicy;
use App\Domain\Automation\Services\GuidedConditionService;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Enums\MetricUnit;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\IoTDashboard\Models\IoTDashboard;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use App\Domain\Telemetry\Enums\ValidationStatus;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use App\Filament\Admin\Pages\IoTDashboard as IoTDashboardPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('renders threshold status cards with normal, alert, offline, no-data, and inactive states', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($admin);

    $fixture = createThresholdStatusCardFixture();
    $normalRecordedAt = now()->subMinute();
    $alertRecordedAt = now()->subSeconds(30);
    $alertTriggeredAt = now()->subMinutes(5);
    $offlineRecordedAt = now()->subHours(5);
    $inactiveRecordedAt = now()->subMinute();

    createThresholdStatusCardTelemetryLog($fixture['devices']['normal'], $fixture['topic'], 4.2, $normalRecordedAt);
    createThresholdStatusCardTelemetryLog($fixture['devices']['alert'], $fixture['topic'], 9.3, $alertRecordedAt);
    createThresholdStatusCardTelemetryLog($fixture['devices']['offline'], $fixture['topic'], 4.5, $offlineRecordedAt);
    createThresholdStatusCardTelemetryLog($fixture['devices']['no_data'], $fixture['topic'], 4.0, now()->subHours(6));
    createThresholdStatusCardTelemetryLog($fixture['devices']['inactive'], $fixture['topic'], 4.4, $inactiveRecordedAt);
    Alert::query()->create([
        'organization_id' => $fixture['organization']->id,
        'threshold_policy_id' => $fixture['policies']['alert']->id,
        'device_id' => $fixture['devices']['alert']->id,
        'parameter_definition_id' => $fixture['parameter']->id,
        'alerted_at' => $alertTriggeredAt,
    ]);

    $response = $this->getJson(route('admin.iot-dashboard.dashboards.snapshots', [
        'dashboard' => $fixture['dashboard'],
    ]));

    $response->assertOk();

    $cardsByTitle = collect($response->json('widgets'))
        ->keyBy('id')
        ->mapWithKeys(function (array $widgetSnapshot) use ($fixture): array {
            $title = $fixture['widgetTitles'][(int) $widgetSnapshot['id']] ?? (string) $widgetSnapshot['id'];

            return [$title => $widgetSnapshot];
        });

    expect($cardsByTitle)->toHaveCount(5)
        ->and(data_get($cardsByTitle['CLD 03 - 02 Threshold Status'], 'type'))->toBe('threshold_status_card')
        ->and(data_get($cardsByTitle['CLD 03 - 02 Threshold Status'], 'card.status'))->toBe('normal')
        ->and(data_get($cardsByTitle['CLD 03 - 02 Threshold Status'], 'card.status_label'))->toBe('NORMAL')
        ->and(data_get($cardsByTitle['CLD 03 - 02 Threshold Status'], 'card.alert_triggered_at'))->toBeNull()
        ->and(data_get($cardsByTitle['CLD 03 - 02 Threshold Status'], 'card.rule_label'))->toBe('Outside 2°C and 8°C')
        ->and(data_get($cardsByTitle['CLD 03 - 02 Threshold Status'], 'card.current_value_display'))->toBe('4.2°C')
        ->and(data_get($cardsByTitle['CLD 04 - 02 Threshold Status'], 'card.status'))->toBe('alert')
        ->and(data_get($cardsByTitle['CLD 04 - 02 Threshold Status'], 'card.status_label'))->toBe('ALERT')
        ->and(data_get($cardsByTitle['CLD 04 - 02 Threshold Status'], 'card.threshold_state'))->toBe('alert')
        ->and(data_get($cardsByTitle['CLD 04 - 02 Threshold Status'], 'card.threshold_breached_at'))->toBe($alertTriggeredAt->toIso8601String())
        ->and(data_get($cardsByTitle['CLD 04 - 02 Threshold Status'], 'card.alert_triggered_at'))->toBe($alertTriggeredAt->toIso8601String())
        ->and((string) data_get($cardsByTitle['CLD 04 - 02 Threshold Status'], 'card.edit_url'))->toContain('/threshold-policies/')
        ->and(data_get($cardsByTitle['CLD 05 - 02 Threshold Status'], 'card.status'))->toBe('offline')
        ->and(data_get($cardsByTitle['CLD 05 - 02 Threshold Status'], 'card.connection_state'))->toBe('OFFLINE')
        ->and(data_get($cardsByTitle['CLD 05 - 02 Threshold Status'], 'card.threshold_state'))->toBe('normal')
        ->and(data_get($cardsByTitle['CLD 05 - 02 Threshold Status'], 'card.threshold_breached_at'))->toBeNull()
        ->and(data_get($cardsByTitle['CLD 05 - 02 Threshold Status'], 'card.last_online_at'))->toBe($fixture['devices']['offline']->lastSeenAt()?->toIso8601String())
        ->and(data_get($cardsByTitle['CLD 05 - 02 Threshold Status'], 'card.current_value_display'))->toBe('4.5°C')
        ->and(data_get($cardsByTitle['CLD 05 - 02 Threshold Status'], 'card.last_value_display'))->toBe('4.5°C')
        ->and(data_get($cardsByTitle['CLD 05 - 02 Threshold Status'], 'card.last_value_recorded_at'))->toBe($offlineRecordedAt->toIso8601String())
        ->and(data_get($cardsByTitle['CLD 05 - 02 Threshold Status'], 'card.display_timestamp'))->toBe($fixture['devices']['offline']->lastSeenAt()?->toIso8601String())
        ->and(data_get($cardsByTitle['CLD 05 - 02 Threshold Status'], 'card.alert_triggered_at'))->toBeNull()
        ->and(data_get($cardsByTitle['CLD 06 - 02 Threshold Status'], 'card.status'))->toBe('no_data')
        ->and(data_get($cardsByTitle['CLD 06 - 02 Threshold Status'], 'card.current_value_display'))->toBe('—')
        ->and(data_get($cardsByTitle['CLD 06 - 02 Threshold Status'], 'card.alert_triggered_at'))->toBeNull()
        ->and(data_get($cardsByTitle['CLD 07 - 02 Threshold Status'], 'card.status'))->toBe('inactive')
        ->and(data_get($cardsByTitle['CLD 07 - 02 Threshold Status'], 'card.alert_triggered_at'))->toBeNull()
        ->and(data_get($cardsByTitle['CLD 07 - 02 Threshold Status'], 'card.status_label'))->toBe('INACTIVE');
});

it('reflects threshold policy edits in the next card snapshot without changing widget config', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($admin);

    $fixture = createThresholdStatusCardFixture();
    $alertPolicy = $fixture['policies']['alert'];
    $alertWidget = $fixture['widgets']['alert'];

    createThresholdStatusCardTelemetryLog($fixture['devices']['alert'], $fixture['topic'], 9.3, now()->subMinute());

    $beforeResponse = $this->getJson(route('admin.iot-dashboard.dashboards.snapshots', [
        'dashboard' => $fixture['dashboard'],
        'widget' => $alertWidget->id,
    ]));

    expect(data_get($beforeResponse->json('widgets.0.card'), 'status'))->toBe('alert');

    $originalConfig = $alertWidget->configArray();

    $updatedCondition = app(GuidedConditionService::class)->fromLegacyBounds(2.0, 10.0);

    $alertPolicy->update([
        'minimum_value' => 2,
        'maximum_value' => 10,
        'condition_mode' => $updatedCondition['condition_mode'],
        'guided_condition' => $updatedCondition['guided_condition'],
        'condition_json_logic' => $updatedCondition['condition_json_logic'],
    ]);

    $afterResponse = $this->getJson(route('admin.iot-dashboard.dashboards.snapshots', [
        'dashboard' => $fixture['dashboard'],
        'widget' => $alertWidget->id,
    ]));

    expect(data_get($afterResponse->json('widgets.0.card'), 'status'))->toBe('normal')
        ->and(data_get($afterResponse->json('widgets.0.card'), 'alert_triggered_at'))->toBeNull()
        ->and($alertWidget->fresh()->configArray())->toBe($originalConfig);
});

it('publishes threshold status card policy bindings in widget bootstrap payloads', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($admin);

    $fixture = createThresholdStatusCardFixture();
    $targetWidget = $fixture['widgets']['normal'];
    $targetPolicy = $fixture['policies']['normal'];

    $page = livewire(IoTDashboardPage::class)
        ->set('dashboardId', $fixture['dashboard']->id)
        ->instance();

    $widgetPayload = collect($page->getWidgetBootstrapPayloadProperty())
        ->firstWhere('id', $targetWidget->id);

    expect($widgetPayload)->not->toBeNull()
        ->and(data_get($widgetPayload, 'type'))->toBe('threshold_status_card')
        ->and(data_get($widgetPayload, 'policy_id'))->toBe($targetPolicy->id)
        ->and(data_get($widgetPayload, 'use_websocket'))->toBeFalse()
        ->and(data_get($widgetPayload, 'use_polling'))->toBeTrue()
        ->and(data_get($widgetPayload, 'lookback_minutes'))->toBe(180)
        ->and(data_get($widgetPayload, 'max_points'))->toBe(1);
});

/**
 * @return array{
 *     organization: Organization,
 *     dashboard: IoTDashboard,
 *     topic: SchemaVersionTopic,
 *     parameter: ParameterDefinition,
 *     devices: array{
 *         normal: Device,
 *         alert: Device,
 *         offline: Device,
 *         no_data: Device,
 *         inactive: Device
 *     },
 *     policies: array{
 *         normal: AutomationThresholdPolicy,
 *         alert: AutomationThresholdPolicy,
 *         offline: AutomationThresholdPolicy,
 *         no_data: AutomationThresholdPolicy,
 *         inactive: AutomationThresholdPolicy
 *     },
 *     widgets: array{
 *         normal: IoTDashboardWidget,
 *         alert: IoTDashboardWidget,
 *         offline: IoTDashboardWidget,
 *         no_data: IoTDashboardWidget,
 *         inactive: IoTDashboardWidget
 *     },
 *     widgetTitles: array<int, string>
 * }
 */
function createThresholdStatusCardFixture(): array
{
    $organization = Organization::factory()->create();
    $deviceType = DeviceType::factory()->forOrganization($organization->id)->mqtt()->create();
    $schema = DeviceSchema::factory()->forDeviceType($deviceType)->create([
        'name' => 'Cold Room Schema',
    ]);
    $schemaVersion = DeviceSchemaVersion::factory()->active()->create([
        'device_schema_id' => $schema->id,
    ]);
    $topic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'key' => 'telemetry',
        'label' => 'Telemetry',
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
    $profile = AutomationNotificationProfile::factory()->sms()->create([
        'organization_id' => $organization->id,
        'name' => 'Cold Room SMS',
        'body' => 'Alert body',
    ]);

    $devices = [
        'normal' => Device::factory()->create([
            'organization_id' => $organization->id,
            'device_type_id' => $deviceType->id,
            'device_schema_version_id' => $schemaVersion->id,
            'name' => 'CLD 03 - 02',
            'connection_state' => 'online',
            'last_seen_at' => now(),
            'presence_timeout_seconds' => 600,
        ]),
        'alert' => Device::factory()->create([
            'organization_id' => $organization->id,
            'device_type_id' => $deviceType->id,
            'device_schema_version_id' => $schemaVersion->id,
            'name' => 'CLD 04 - 02',
            'connection_state' => 'online',
            'last_seen_at' => now(),
            'presence_timeout_seconds' => 600,
        ]),
        'offline' => Device::factory()->create([
            'organization_id' => $organization->id,
            'device_type_id' => $deviceType->id,
            'device_schema_version_id' => $schemaVersion->id,
            'name' => 'CLD 05 - 02',
            'connection_state' => 'offline',
            'last_seen_at' => now()->subHours(2),
            'presence_timeout_seconds' => 600,
        ]),
        'no_data' => Device::factory()->create([
            'organization_id' => $organization->id,
            'device_type_id' => $deviceType->id,
            'device_schema_version_id' => $schemaVersion->id,
            'name' => 'CLD 06 - 02',
            'connection_state' => 'online',
            'last_seen_at' => now(),
            'presence_timeout_seconds' => 600,
        ]),
        'inactive' => Device::factory()->create([
            'organization_id' => $organization->id,
            'device_type_id' => $deviceType->id,
            'device_schema_version_id' => $schemaVersion->id,
            'name' => 'CLD 07 - 02',
            'connection_state' => 'online',
            'last_seen_at' => now(),
            'presence_timeout_seconds' => 600,
        ]),
    ];

    $policies = [
        'normal' => AutomationThresholdPolicy::factory()->create([
            'organization_id' => $organization->id,
            'device_id' => $devices['normal']->id,
            'parameter_definition_id' => $parameter->id,
            'notification_profile_id' => $profile->id,
            'name' => 'CLD 03 - 02 Threshold',
            'minimum_value' => 2,
            'maximum_value' => 8,
            'sort_order' => 1,
        ]),
        'alert' => AutomationThresholdPolicy::factory()->create([
            'organization_id' => $organization->id,
            'device_id' => $devices['alert']->id,
            'parameter_definition_id' => $parameter->id,
            'notification_profile_id' => $profile->id,
            'name' => 'CLD 04 - 02 Threshold',
            'minimum_value' => 2,
            'maximum_value' => 8,
            'sort_order' => 2,
        ]),
        'offline' => AutomationThresholdPolicy::factory()->create([
            'organization_id' => $organization->id,
            'device_id' => $devices['offline']->id,
            'parameter_definition_id' => $parameter->id,
            'notification_profile_id' => $profile->id,
            'name' => 'CLD 05 - 02 Threshold',
            'minimum_value' => 2,
            'maximum_value' => 8,
            'sort_order' => 3,
        ]),
        'no_data' => AutomationThresholdPolicy::factory()->create([
            'organization_id' => $organization->id,
            'device_id' => $devices['no_data']->id,
            'parameter_definition_id' => $parameter->id,
            'notification_profile_id' => $profile->id,
            'name' => 'CLD 06 - 02 Threshold',
            'minimum_value' => 2,
            'maximum_value' => 8,
            'sort_order' => 4,
        ]),
        'inactive' => AutomationThresholdPolicy::factory()->create([
            'organization_id' => $organization->id,
            'device_id' => $devices['inactive']->id,
            'parameter_definition_id' => $parameter->id,
            'notification_profile_id' => $profile->id,
            'name' => 'CLD 07 - 02 Threshold',
            'minimum_value' => 2,
            'maximum_value' => 8,
            'is_active' => false,
            'sort_order' => 5,
        ]),
    ];

    $dashboard = IoTDashboard::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Cold Room Status',
        'slug' => 'cold-room-status',
        'description' => 'Threshold status card dashboard',
        'is_active' => true,
        'refresh_interval_seconds' => 15,
    ]);

    $widgets = [];
    $widgetTitles = [];
    $states = ['normal', 'alert', 'offline', 'no_data', 'inactive'];

    foreach ($states as $index => $state) {
        $widget = IoTDashboardWidget::query()->create([
            'iot_dashboard_id' => $dashboard->id,
            'device_id' => $devices[$state]->id,
            'schema_version_topic_id' => $topic->id,
            'type' => 'threshold_status_card',
            'title' => $devices[$state]->name.' Threshold Status',
            'config' => [
                'policy_id' => $policies[$state]->id,
                'transport' => [
                    'use_websocket' => false,
                    'use_polling' => true,
                    'polling_interval_seconds' => 15,
                ],
                'window' => [
                    'lookback_minutes' => 180,
                    'max_points' => 1,
                ],
            ],
            'layout' => [
                'x' => ($index % 2) * 12,
                'y' => intdiv($index, 2) * 3,
                'w' => 12,
                'h' => 3,
                'columns' => 24,
                'card_height_px' => 320,
            ],
            'sequence' => $index + 1,
        ]);

        $widgets[$state] = $widget;
        $widgetTitles[(int) $widget->id] = $widget->title;
    }

    return [
        'organization' => $organization,
        'dashboard' => $dashboard,
        'topic' => $topic,
        'parameter' => $parameter,
        'devices' => $devices,
        'policies' => $policies,
        'widgets' => $widgets,
        'widgetTitles' => $widgetTitles,
    ];
}

function createThresholdStatusCardTelemetryLog(Device $device, SchemaVersionTopic $topic, float $value, Carbon $recordedAt): DeviceTelemetryLog
{
    return DeviceTelemetryLog::factory()
        ->forDevice($device)
        ->forTopic($topic)
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
