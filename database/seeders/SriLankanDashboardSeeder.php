<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Automation\Models\AutomationThresholdPolicy;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\IoTDashboard\Enums\WidgetType;
use App\Domain\IoTDashboard\Models\IoTDashboard;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\Shared\Models\Organization;
use Illuminate\Database\Seeder;

class SriLankanDashboardSeeder extends Seeder
{
    private const string STATUS_DASHBOARD_SLUG = 'srilankan-cold-room-status';

    private const string HISTORY_DASHBOARD_SLUG = 'srilankan-cold-room-history';

    private const string DEVICE_HISTORY_TITLE_SUFFIX = ' Temperature History';

    /**
     * @var list<array{
     *     device_name: string,
     *     room_name: string,
     *     parameter_key: string,
     *     minimum_value: float,
     *     maximum_value: float
     * }>
     */
    private const array STATUS_DEVICE_CARDS = [
        ['device_name' => 'CLD 02', 'room_name' => 'CLD 02', 'parameter_key' => 'temperature', 'minimum_value' => 2.0, 'maximum_value' => 8.0],
        ['device_name' => 'CLD 03', 'room_name' => 'CLD 03', 'parameter_key' => 'temperature', 'minimum_value' => 2.0, 'maximum_value' => 8.0],
        ['device_name' => 'CLD 04-01', 'room_name' => 'CLD 04-01', 'parameter_key' => 'temperature', 'minimum_value' => 2.0, 'maximum_value' => 8.0],
        ['device_name' => 'CLD 05-01', 'room_name' => 'CLD 05-01', 'parameter_key' => 'temperature', 'minimum_value' => 15.0, 'maximum_value' => 25.0],
        ['device_name' => 'CLD 06', 'room_name' => 'CLD 06', 'parameter_key' => 'temperature', 'minimum_value' => 15.0, 'maximum_value' => 25.0],
        ['device_name' => 'CLD 07-02', 'room_name' => 'CLD 07-02', 'parameter_key' => 'temperature', 'minimum_value' => 2.0, 'maximum_value' => 8.0],
        ['device_name' => 'CLD 08-04', 'room_name' => 'CLD 08-04', 'parameter_key' => 'temperature_2', 'minimum_value' => -20.0, 'maximum_value' => 0.0],
        ['device_name' => 'CLD 09-02', 'room_name' => 'CLD 09-02', 'parameter_key' => 'temperature', 'minimum_value' => 15.0, 'maximum_value' => 25.0],
        ['device_name' => 'CLD 10', 'room_name' => 'CLD 10', 'parameter_key' => 'temperature', 'minimum_value' => 15.0, 'maximum_value' => 25.0],
        ['device_name' => 'CLD 11-01', 'room_name' => 'CLD 11-01', 'parameter_key' => 'temperature', 'minimum_value' => 15.0, 'maximum_value' => 25.0],
    ];

    public function run(): void
    {
        $organization = Organization::query()
            ->where('slug', SriLankanMigrationSeeder::ORGANIZATION_SLUG)
            ->first();

        if (! $organization instanceof Organization) {
            $this->command?->warn('SriLankan organization not found. Skipping SriLankan dashboard seed.');

            return;
        }

        $configuredScopes = $this->resolveConfiguredCardScopes($organization);

        if ($configuredScopes === []) {
            $this->command?->warn('SriLankan telemetry scope not found. Skipping SriLankan dashboard seed.');

            return;
        }

        $statusDashboard = IoTDashboard::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'slug' => self::STATUS_DASHBOARD_SLUG,
            ],
            [
                'name' => 'Cold Room Status',
                'description' => 'SriLankan Airlines Limited · Realtime device telemetry with policy-backed status cards.',
                'is_active' => true,
                'refresh_interval_seconds' => 15,
            ],
        );

        $historyDashboard = IoTDashboard::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'slug' => self::HISTORY_DASHBOARD_SLUG,
            ],
            [
                'name' => 'Cold Room History',
                'description' => 'SriLankan Airlines Limited · Temperature history for the communicating cold-room sensors.',
                'is_active' => true,
                'refresh_interval_seconds' => 30,
            ],
        );

        $policies = $this->syncThresholdPolicies($organization, $configuredScopes);
        $this->syncThresholdStatusWidgets($statusDashboard, $configuredScopes, $policies);
        $this->syncDeviceHistoryCharts($historyDashboard, $configuredScopes);
    }

    /**
     * @param  list<array{
     *     card: array{
     *         device_name: string,
     *         room_name: string,
     *         parameter_key: string,
     *         minimum_value: float,
     *         maximum_value: float
     *     },
     *     device: Device,
     *     topic: SchemaVersionTopic,
     *     parameter: ParameterDefinition
     * }>  $configuredScopes
     * @return array<int, AutomationThresholdPolicy>
     */
    private function syncThresholdPolicies(Organization $organization, array $configuredScopes): array
    {
        $policiesByDeviceId = [];

        foreach ($configuredScopes as $index => $scope) {
            $policy = AutomationThresholdPolicy::query()
                ->withTrashed()
                ->firstOrNew([
                    'organization_id' => $organization->id,
                    'device_id' => $scope['device']->id,
                    'parameter_definition_id' => $scope['parameter']->id,
                    'legacy_alert_rule_id' => null,
                ]);

            $policy->fill([
                'name' => $scope['card']['room_name'].' Temperature Threshold',
                'minimum_value' => $scope['card']['minimum_value'],
                'maximum_value' => $scope['card']['maximum_value'],
                'is_active' => true,
                'cooldown_value' => 1,
                'cooldown_unit' => 'day',
                'sort_order' => $index + 1,
                'legacy_metadata' => [
                    'seed_source' => 'sri_lankan_dashboard',
                    'room_name' => $scope['card']['room_name'],
                ],
                'deleted_at' => null,
            ]);
            $policy->save();

            $policiesByDeviceId[(int) $scope['device']->id] = $policy;
        }

        return $policiesByDeviceId;
    }

    /**
     * @param  list<array{
     *     card: array{
     *         device_name: string,
     *         room_name: string,
     *         parameter_key: string,
     *         minimum_value: float,
     *         maximum_value: float
     *     },
     *     device: Device,
     *     topic: SchemaVersionTopic,
     *     parameter: ParameterDefinition
     * }>  $configuredScopes
     * @param  array<int, AutomationThresholdPolicy>  $policies
     */
    private function syncThresholdStatusWidgets(IoTDashboard $dashboard, array $configuredScopes, array $policies): void
    {
        $expectedTitles = [];

        foreach ($configuredScopes as $index => $scope) {
            $policy = $policies[(int) $scope['device']->id] ?? null;

            if (! $policy instanceof AutomationThresholdPolicy) {
                continue;
            }

            $title = $scope['card']['room_name'];
            $expectedTitles[] = $title;

            IoTDashboardWidget::query()->updateOrCreate(
                [
                    'iot_dashboard_id' => $dashboard->id,
                    'title' => $title,
                ],
                [
                    'device_id' => $scope['device']->id,
                    'schema_version_topic_id' => $scope['topic']->id,
                    'type' => WidgetType::ThresholdStatusCard->value,
                    'config' => [
                        'policy_id' => (int) $policy->id,
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
                    'layout' => $this->thresholdStatusCardLayout($index),
                    'sequence' => $index + 1,
                ],
            );
        }

        IoTDashboardWidget::query()
            ->where('iot_dashboard_id', $dashboard->id)
            ->whereIn('type', [
                WidgetType::ThresholdStatusCard->value,
                WidgetType::ThresholdStatusGrid->value,
            ])
            ->when(
                $expectedTitles !== [],
                fn ($query) => $query->whereNotIn('title', $expectedTitles),
            )
            ->delete();

        $this->deleteUnexpectedWidgetTypes($dashboard, [
            WidgetType::ThresholdStatusCard->value,
            WidgetType::ThresholdStatusGrid->value,
        ]);
    }

    /**
     * @param  list<array{
     *     card: array{
     *         device_name: string,
     *         room_name: string,
     *         parameter_key: string,
     *         minimum_value: float,
     *         maximum_value: float
     *     },
     *     device: Device,
     *     topic: SchemaVersionTopic,
     *     parameter: ParameterDefinition
     * }>  $configuredScopes
     */
    private function syncDeviceHistoryCharts(IoTDashboard $dashboard, array $configuredScopes): void
    {
        $expectedTitles = [];

        foreach ($configuredScopes as $index => $scope) {
            $title = $scope['card']['room_name'].self::DEVICE_HISTORY_TITLE_SUFFIX;
            $expectedTitles[] = $title;

            IoTDashboardWidget::query()->updateOrCreate(
                [
                    'iot_dashboard_id' => $dashboard->id,
                    'title' => $title,
                ],
                [
                    'device_id' => $scope['device']->id,
                    'schema_version_topic_id' => $scope['topic']->id,
                    'type' => WidgetType::LineChart->value,
                    'config' => [
                        'series' => [[
                            'key' => $scope['parameter']->key,
                            'label' => $scope['parameter']->label,
                            'color' => '#0ea5e9',
                        ]],
                        'transport' => [
                            'use_websocket' => false,
                            'use_polling' => true,
                            'polling_interval_seconds' => 30,
                        ],
                        'window' => [
                            'lookback_minutes' => 720,
                            'max_points' => 240,
                        ],
                    ],
                    'layout' => $this->deviceHistoryChartLayout($index),
                    'sequence' => $index + 1,
                ],
            );
        }

        IoTDashboardWidget::query()
            ->where('iot_dashboard_id', $dashboard->id)
            ->where('type', WidgetType::LineChart->value)
            ->when(
                $expectedTitles !== [],
                fn ($query) => $query->whereNotIn('title', $expectedTitles),
            )
            ->delete();

        $this->deleteUnexpectedWidgetTypes($dashboard, [
            WidgetType::LineChart->value,
        ]);
    }

    /**
     * @return list<array{
     *     card: array{
     *         device_name: string,
     *         room_name: string,
     *         parameter_key: string,
     *         minimum_value: float,
     *         maximum_value: float
     *     },
     *     device: Device,
     *     topic: SchemaVersionTopic,
     *     parameter: ParameterDefinition
     * }>
     */
    private function resolveConfiguredCardScopes(Organization $organization): array
    {
        $devices = Device::query()
            ->with([
                'schemaVersion.topics' => fn ($query) => $query
                    ->where('direction', TopicDirection::Publish->value)
                    ->where('key', 'telemetry')
                    ->with([
                        'parameters' => fn ($query) => $query
                            ->where('is_active', true)
                            ->orderBy('sequence')
                            ->orderBy('id'),
                    ]),
            ])
            ->where('organization_id', $organization->id)
            ->whereIn('name', array_column(self::STATUS_DEVICE_CARDS, 'device_name'))
            ->get()
            ->keyBy('name');

        $scopes = [];

        foreach (self::STATUS_DEVICE_CARDS as $card) {
            $device = $devices->get($card['device_name']);

            if (! $device instanceof Device) {
                continue;
            }

            ['topic' => $topic, 'parameter' => $parameter] = $this->resolveDeviceParameterScope(
                $device,
                $card['parameter_key'],
            );

            if (! $topic instanceof SchemaVersionTopic || ! $parameter instanceof ParameterDefinition) {
                continue;
            }

            $scopes[] = [
                'card' => $card,
                'device' => $device,
                'topic' => $topic,
                'parameter' => $parameter,
            ];
        }

        return $scopes;
    }

    /**
     * @return array{topic: SchemaVersionTopic|null, parameter: ParameterDefinition|null}
     */
    private function resolveDeviceParameterScope(Device $device, string $parameterKey): array
    {
        foreach ($device->schemaVersion?->topics ?? [] as $topic) {
            if (! $topic instanceof SchemaVersionTopic) {
                continue;
            }

            $parameter = $topic->parameters
                ->first(fn (ParameterDefinition $parameter): bool => $parameter->key === $parameterKey);

            if ($parameter instanceof ParameterDefinition) {
                return [
                    'topic' => $topic,
                    'parameter' => $parameter,
                ];
            }
        }

        return [
            'topic' => null,
            'parameter' => null,
        ];
    }

    /**
     * @param  list<string>  $allowedTypes
     */
    private function deleteUnexpectedWidgetTypes(IoTDashboard $dashboard, array $allowedTypes): void
    {
        IoTDashboardWidget::query()
            ->where('iot_dashboard_id', $dashboard->id)
            ->whereNotIn('type', $allowedTypes)
            ->delete();
    }

    /**
     * @return array{x: int, y: int, w: int, h: int, columns: int, card_height_px: int}
     */
    private function thresholdStatusCardLayout(int $index): array
    {
        $column = $index % 5;
        $row = intdiv($index, 5);

        return [
            'x' => $column * 4,
            'y' => $row * 3,
            'w' => 4,
            'h' => 3,
            'columns' => 24,
            'card_height_px' => 288,
        ];
    }

    /**
     * @return array{x: int, y: int, w: int, h: int, columns: int, card_height_px: int}
     */
    private function deviceHistoryChartLayout(int $index): array
    {
        $row = intdiv($index, 2);
        $column = $index % 2;

        return [
            'x' => $column * 12,
            'y' => $row * 4,
            'w' => 12,
            'h' => 4,
            'columns' => 24,
            'card_height_px' => 384,
        ];
    }
}
