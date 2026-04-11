<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Automation\Models;

use App\Domain\Alerts\Models\NotificationProfile;
use App\Domain\Alerts\Models\ThresholdPolicy;
use App\Domain\Automation\Services\GuidedConditionService;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\Shared\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThresholdPolicy>
 */
class AutomationThresholdPolicyFactory extends Factory
{
    protected $model = ThresholdPolicy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minimumValue = $this->faker->numberBetween(-20, 15);
        $maximumValue = $minimumValue + $this->faker->numberBetween(1, 15);

        return [
            'organization_id' => Organization::factory(),
            'device_id' => Device::factory(),
            'parameter_definition_id' => ParameterDefinition::factory(),
            'name' => $this->faker->unique()->words(3, true),
            'minimum_value' => $minimumValue,
            'maximum_value' => $maximumValue,
            'condition_mode' => function (array $attributes): string {
                return $this->conditionFromAttributes($attributes)['condition_mode'];
            },
            'guided_condition' => function (array $attributes): array {
                return $this->conditionFromAttributes($attributes)['guided_condition'];
            },
            'condition_json_logic' => function (array $attributes): array {
                return $this->conditionFromAttributes($attributes)['condition_json_logic'];
            },
            'is_active' => true,
            'cooldown_value' => 1,
            'cooldown_unit' => $this->faker->randomElement(['minute', 'hour', 'day']),
            'notification_profile_id' => NotificationProfile::factory(),
            'sort_order' => 0,
            'managed_workflow_id' => null,
            'legacy_alert_rule_id' => null,
            'legacy_metadata' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function withoutNotificationProfile(): static
    {
        return $this->state(fn (): array => [
            'notification_profile_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     condition_mode: string,
     *     guided_condition: array{
     *         left: string,
     *         operator: string,
     *         right: float,
     *         right_secondary?: float
     *     },
     *     condition_json_logic: array<string, mixed>
     * }
     */
    private function conditionFromAttributes(array $attributes): array
    {
        return app(GuidedConditionService::class)->fromLegacyBounds(
            minimumValue: isset($attributes['minimum_value']) && is_numeric($attributes['minimum_value'])
                ? (float) $attributes['minimum_value']
                : null,
            maximumValue: isset($attributes['maximum_value']) && is_numeric($attributes['maximum_value'])
                ? (float) $attributes['maximum_value']
                : null,
        );
    }
}
