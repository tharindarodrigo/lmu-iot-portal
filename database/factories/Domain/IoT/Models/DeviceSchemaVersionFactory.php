<?php

declare(strict_types=1);

namespace Database\Factories\Domain\IoT\Models;

use App\Domain\IoT\Models\DeviceSchema;
use App\Domain\IoT\Models\DeviceSchemaVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\IoT\Models\DeviceSchemaVersion>
 */
class DeviceSchemaVersionFactory extends Factory
{
    protected $model = DeviceSchemaVersion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_schema_id' => DeviceSchema::factory(),
            'version' => 1,
            'status' => 'draft',
            'notes' => $this->faker->optional()->sentence,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
        ]);
    }
}
