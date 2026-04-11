<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $widgets = DB::table('iot_dashboard_widgets')
            ->where('type', 'threshold_status_grid')
            ->orderBy('id')
            ->get();

        foreach ($widgets as $widget) {
            $this->migrateThresholdStatusGridWidget($widget);
        }
    }

    public function down(): void
    {
        // This migration intentionally does not recreate the legacy grid widgets.
    }

    private function migrateThresholdStatusGridWidget(object $widget): void
    {
        $dashboard = DB::table('iot_dashboards')
            ->where('id', $widget->iot_dashboard_id)
            ->first(['id', 'organization_id']);

        if (! $dashboard instanceof stdClass) {
            DB::table('iot_dashboard_widgets')->where('id', $widget->id)->delete();

            return;
        }

        $config = $this->decodeJsonObject($widget->config ?? null);
        $layout = $this->decodeJsonObject($widget->layout ?? null);
        $policyIds = $this->resolvePolicyIds((int) $dashboard->organization_id, $config);

        if ($policyIds === []) {
            DB::table('iot_dashboard_widgets')->where('id', $widget->id)->delete();

            return;
        }

        DB::transaction(function () use ($widget, $dashboard, $layout, $policyIds): void {
            DB::table('iot_dashboard_widgets')
                ->where('iot_dashboard_id', $widget->iot_dashboard_id)
                ->where('id', '!=', $widget->id)
                ->where('sequence', '>', (int) $widget->sequence)
                ->increment('sequence', count($policyIds) - 1);

            $layoutWidth = max(4, $this->toInt($layout['w'] ?? null, 24));
            $layoutX = $this->toInt($layout['x'] ?? null, 0);
            $layoutY = $this->toInt($layout['y'] ?? null, 0);
            $cardWidth = $this->determineCardWidth($layoutWidth, count($policyIds));
            $cardsPerRow = max(1, intdiv($layoutWidth, $cardWidth));

            foreach ($policyIds as $index => $policyId) {
                $policy = $this->fetchPolicySnapshot((int) $dashboard->organization_id, $policyId);

                if (! $policy instanceof stdClass) {
                    continue;
                }

                $row = intdiv($index, $cardsPerRow);
                $column = $index % $cardsPerRow;
                $title = trim((string) ($policy->device_name ?? $policy->policy_name ?? 'Threshold Status'));

                DB::table('iot_dashboard_widgets')->insert([
                    'iot_dashboard_id' => $widget->iot_dashboard_id,
                    'device_id' => $policy->device_id,
                    'schema_version_topic_id' => $policy->schema_version_topic_id,
                    'type' => 'threshold_status_card',
                    'title' => $title,
                    'config' => json_encode([
                        'policy_id' => $policyId,
                        'transport' => [
                            'use_websocket' => false,
                            'use_polling' => true,
                            'polling_interval_seconds' => 15,
                        ],
                        'window' => [
                            'lookback_minutes' => 180,
                            'max_points' => 1,
                        ],
                    ], JSON_THROW_ON_ERROR),
                    'layout' => json_encode([
                        'x' => $layoutX + ($column * $cardWidth),
                        'y' => $layoutY + ($row * 3),
                        'w' => $cardWidth,
                        'h' => 3,
                        'columns' => 24,
                        'card_height_px' => 320,
                    ], JSON_THROW_ON_ERROR),
                    'sequence' => (int) $widget->sequence + $index,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('iot_dashboard_widgets')->where('id', $widget->id)->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<int>
     */
    private function resolvePolicyIds(int $organizationId, array $config): array
    {
        $scope = in_array($config['scope'] ?? null, ['all_active', 'selected', 'device_cards'], true)
            ? (string) $config['scope']
            : 'all_active';

        if ($scope === 'selected') {
            $selectedPolicyIds = array_values(array_filter(
                array_map(
                    static fn (mixed $policyId): ?int => is_numeric($policyId) ? (int) $policyId : null,
                    is_array($config['policy_ids'] ?? null) ? $config['policy_ids'] : [],
                ),
                static fn (?int $policyId): bool => $policyId !== null && $policyId > 0,
            ));

            if ($selectedPolicyIds === []) {
                return [];
            }

            $existingPolicyIds = DB::table('threshold_policies')
                ->where('organization_id', $organizationId)
                ->whereIn('id', $selectedPolicyIds)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(static fn (mixed $policyId): int => (int) $policyId)
                ->all();

            return array_values(array_filter(
                $selectedPolicyIds,
                static fn (int $policyId): bool => in_array($policyId, $existingPolicyIds, true),
            ));
        }

        if ($scope === 'device_cards') {
            $policyIds = [];

            foreach ($this->normalizeDeviceCards($config['device_cards'] ?? null) as $deviceCard) {
                $policyId = $this->resolveOrCreatePolicyFromDeviceCard($organizationId, $deviceCard);

                if ($policyId !== null) {
                    $policyIds[] = $policyId;
                }
            }

            return array_values(array_unique($policyIds));
        }

        return DB::table('threshold_policies')
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('id')
            ->map(static fn (mixed $policyId): int => (int) $policyId)
            ->all();
    }

    /**
     * @return list<array{device_id: int, label: string, parameter_key: string, minimum_value: float|null, maximum_value: float|null}>
     */
    private function normalizeDeviceCards(mixed $deviceCards): array
    {
        if (! is_array($deviceCards)) {
            return [];
        }

        $normalizedCards = [];

        foreach ($deviceCards as $deviceCard) {
            if (! is_array($deviceCard) || ! is_numeric($deviceCard['device_id'] ?? null)) {
                continue;
            }

            $parameterKey = is_string($deviceCard['parameter_key'] ?? null)
                ? trim((string) $deviceCard['parameter_key'])
                : '';

            if ($parameterKey === '') {
                continue;
            }

            $normalizedCards[] = [
                'device_id' => (int) $deviceCard['device_id'],
                'label' => is_string($deviceCard['label'] ?? null) ? trim((string) $deviceCard['label']) : '',
                'parameter_key' => $parameterKey,
                'minimum_value' => is_numeric($deviceCard['minimum_value'] ?? null)
                    ? (float) $deviceCard['minimum_value']
                    : null,
                'maximum_value' => is_numeric($deviceCard['maximum_value'] ?? null)
                    ? (float) $deviceCard['maximum_value']
                    : null,
            ];
        }

        return $normalizedCards;
    }

    /**
     * @param  array{device_id: int, label: string, parameter_key: string, minimum_value: float|null, maximum_value: float|null}  $deviceCard
     */
    private function resolveOrCreatePolicyFromDeviceCard(int $organizationId, array $deviceCard): ?int
    {
        $device = DB::table('devices')
            ->where('id', $deviceCard['device_id'])
            ->where('organization_id', $organizationId)
            ->first(['id', 'name', 'device_schema_version_id']);

        if (! $device instanceof stdClass) {
            return null;
        }

        $parameter = DB::table('parameter_definitions as parameters')
            ->join('schema_version_topics as topics', 'topics.id', '=', 'parameters.schema_version_topic_id')
            ->where('topics.device_schema_version_id', $device->device_schema_version_id)
            ->where('topics.direction', 'publish')
            ->where('parameters.key', $deviceCard['parameter_key'])
            ->where('parameters.is_active', true)
            ->orderBy('parameters.sequence')
            ->orderBy('parameters.id')
            ->select(['parameters.id'])
            ->first();

        if (! $parameter instanceof stdClass) {
            return null;
        }

        $existingPolicyId = DB::table('threshold_policies')
            ->where('organization_id', $organizationId)
            ->where('device_id', $device->id)
            ->where('parameter_definition_id', $parameter->id)
            ->whereNull('deleted_at')
            ->orderBy('is_active', 'desc')
            ->orderBy('id')
            ->value('id');

        if (is_numeric($existingPolicyId)) {
            return (int) $existingPolicyId;
        }

        $condition = $this->conditionFieldsFromBounds(
            minimumValue: $deviceCard['minimum_value'],
            maximumValue: $deviceCard['maximum_value'],
        );

        if ($condition === null) {
            return null;
        }

        $sortOrder = (int) DB::table('threshold_policies')
            ->where('organization_id', $organizationId)
            ->max('sort_order') + 1;

        return (int) DB::table('threshold_policies')->insertGetId([
            'organization_id' => $organizationId,
            'device_id' => $device->id,
            'parameter_definition_id' => $parameter->id,
            'name' => $deviceCard['label'] !== ''
                ? $deviceCard['label'].' Threshold'
                : trim((string) $device->name).' Threshold',
            'minimum_value' => $deviceCard['minimum_value'],
            'maximum_value' => $deviceCard['maximum_value'],
            'condition_mode' => 'guided',
            'guided_condition' => json_encode($condition['guided_condition'], JSON_THROW_ON_ERROR),
            'condition_json_logic' => json_encode($condition['condition_json_logic'], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'cooldown_value' => 1,
            'cooldown_unit' => 'day',
            'notification_profile_id' => null,
            'sort_order' => $sortOrder,
            'managed_workflow_id' => null,
            'legacy_alert_rule_id' => null,
            'legacy_metadata' => json_encode([
                'migrated_from_threshold_status_grid' => true,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function fetchPolicySnapshot(int $organizationId, int $policyId): ?stdClass
    {
        $policy = DB::table('threshold_policies as policies')
            ->join('devices', 'devices.id', '=', 'policies.device_id')
            ->join('parameter_definitions as parameters', 'parameters.id', '=', 'policies.parameter_definition_id')
            ->where('policies.organization_id', $organizationId)
            ->where('policies.id', $policyId)
            ->whereNull('policies.deleted_at')
            ->select([
                'policies.id',
                'policies.name as policy_name',
                'policies.device_id',
                'devices.name as device_name',
                'parameters.schema_version_topic_id',
            ])
            ->first();

        return $policy instanceof stdClass ? $policy : null;
    }

    private function determineCardWidth(int $layoutWidth, int $cardCount): int
    {
        $maxCardsPerRow = max(1, intdiv($layoutWidth, 4));
        $cardsPerRow = max(1, min($cardCount, $maxCardsPerRow));

        return max(4, intdiv($layoutWidth, $cardsPerRow));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function toInt(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) round((float) $value) : $default;
    }

    private function conditionFieldsFromBounds(?float $minimumValue, ?float $maximumValue): ?array
    {
        if ($minimumValue === null && $maximumValue === null) {
            return null;
        }

        if ($minimumValue !== null && $maximumValue !== null) {
            $lowerBound = min($minimumValue, $maximumValue);
            $upperBound = max($minimumValue, $maximumValue);

            return [
                'guided_condition' => [
                    'left' => 'trigger.value',
                    'operator' => 'outside_between',
                    'right' => $lowerBound,
                    'right_secondary' => $upperBound,
                ],
                'condition_json_logic' => [
                    'or' => [
                        [
                            '<' => [
                                ['var' => 'trigger.value'],
                                $lowerBound,
                            ],
                        ],
                        [
                            '>' => [
                                ['var' => 'trigger.value'],
                                $upperBound,
                            ],
                        ],
                    ],
                ],
            ];
        }

        if ($minimumValue !== null) {
            return [
                'guided_condition' => [
                    'left' => 'trigger.value',
                    'operator' => '<',
                    'right' => $minimumValue,
                ],
                'condition_json_logic' => [
                    '<' => [
                        ['var' => 'trigger.value'],
                        $minimumValue,
                    ],
                ],
            ];
        }

        return [
            'guided_condition' => [
                'left' => 'trigger.value',
                'operator' => '>',
                'right' => $maximumValue,
            ],
            'condition_json_logic' => [
                '>' => [
                    ['var' => 'trigger.value'],
                    $maximumValue,
                ],
            ],
        ];
    }
};
