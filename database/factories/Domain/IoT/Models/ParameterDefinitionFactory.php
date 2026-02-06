<?php

declare(strict_types=1);

namespace Database\Factories\Domain\IoT\Models;

use App\Domain\IoT\Enums\ParameterDataType;
use App\Domain\IoT\Models\DeviceSchemaVersion;
use App\Domain\IoT\Models\ParameterDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\IoT\Models\ParameterDefinition>
 */
class ParameterDefinitionFactory extends Factory
{
    protected $model = ParameterDefinition::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dataType = $this->faker->randomElement(ParameterDataType::cases());

        return [
            'device_schema_version_id' => DeviceSchemaVersion::factory(),
            'key' => $this->faker->unique()->slug(2),
            'label' => $this->faker->words(2, true),
            'json_path' => $this->faker->randomElement(['temp', 'status.temp', '$.status.temp']),
            'type' => $dataType,
            'unit' => $dataType === ParameterDataType::Decimal ? $this->faker->randomElement(['Celsius', 'Percent', 'Volts']) : null,
            'required' => $this->faker->boolean(60),
            'is_critical' => $this->faker->boolean(20),
            'validation_rules' => [
                'min' => -40,
                'max' => 85,
            ],
            'validation_error_code' => $this->faker->optional()->lexify('VAL_????'),
            'mutation_expression' => [
                '*' => [
                    ['var' => 'val'],
                    1.0,
                ],
            ],
            'sequence' => $this->faker->numberBetween(1, 10),
            'is_active' => $this->faker->boolean(90),
        ];
    }
}
