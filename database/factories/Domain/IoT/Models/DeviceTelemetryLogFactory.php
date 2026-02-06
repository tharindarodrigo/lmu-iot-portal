<?php

declare(strict_types=1);

namespace Database\Factories\Domain\IoT\Models;

use App\Domain\IoT\Enums\ValidationStatus;
use App\Domain\IoT\Models\Device;
use App\Domain\IoT\Models\DeviceSchemaVersion;
use App\Domain\IoT\Models\DeviceTelemetryLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\IoT\Models\DeviceTelemetryLog>
 */
class DeviceTelemetryLogFactory extends Factory
{
    protected $model = DeviceTelemetryLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $receivedAt = $this->faker->dateTimeBetween('-1 day');
        $recordedAt = $this->faker->dateTimeBetween('-1 day', $receivedAt);

        return [
            'device_id' => Device::factory(),
            'device_schema_version_id' => DeviceSchemaVersion::factory(),
            'raw_payload' => [
                'temp' => $this->faker->randomFloat(2, -10, 40),
                'voltage' => $this->faker->randomFloat(2, 100, 250),
            ],
            'transformed_values' => [
                'temp' => $this->faker->randomFloat(2, -10, 40),
                'voltage' => $this->faker->randomFloat(2, 100, 250),
            ],
            'validation_status' => $this->faker->randomElement(ValidationStatus::cases()),
            'recorded_at' => $recordedAt,
            'received_at' => $receivedAt,
        ];
    }

    public function forDevice(Device $device): static
    {
        return $this->state(fn () => [
            'device_id' => $device->id,
            'device_schema_version_id' => $device->device_schema_version_id,
        ]);
    }
}
