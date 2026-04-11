<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Alerts\Models;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Alerts\Models\ThresholdPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'threshold_policy_id' => ThresholdPolicy::factory(),
            'organization_id' => function (array $attributes): int {
                $policy = ThresholdPolicy::query()->find($attributes['threshold_policy_id']);

                return (int) $policy?->organization_id;
            },
            'device_id' => function (array $attributes): int {
                $policy = ThresholdPolicy::query()->find($attributes['threshold_policy_id']);

                return (int) $policy?->device_id;
            },
            'parameter_definition_id' => function (array $attributes): int {
                $policy = ThresholdPolicy::query()->find($attributes['threshold_policy_id']);

                return (int) $policy?->parameter_definition_id;
            },
            'alerted_at' => $this->faker->dateTimeBetween('-1 day'),
            'alerted_telemetry_log_id' => null,
            'normalized_at' => null,
            'normalized_telemetry_log_id' => null,
            'alert_notification_sent_at' => null,
            'normalized_notification_sent_at' => null,
        ];
    }

    public function normalized(): static
    {
        return $this->state(fn (): array => [
            'normalized_at' => now(),
        ]);
    }
}
