<?php

declare(strict_types=1);

namespace Database\Factories\Domain\DeviceManagement\Models;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\VirtualDeviceLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VirtualDeviceLink>
 */
class VirtualDeviceLinkFactory extends Factory
{
    protected $model = VirtualDeviceLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'virtual_device_id' => Device::factory()->virtual(),
            'source_device_id' => Device::factory(),
            'purpose' => fake()->randomElement(['status', 'energy', 'length']),
            'sequence' => 1,
            'metadata' => [],
        ];
    }
}
