<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_threshold_policies', function (Blueprint $table): void {
            $table->string('condition_mode', 32)->default('guided')->after('maximum_value');
            $table->jsonb('guided_condition')->nullable()->after('condition_mode');
            $table->jsonb('condition_json_logic')->nullable()->after('guided_condition');
        });

        DB::table('automation_threshold_policies')
            ->select(['id', 'minimum_value', 'maximum_value'])
            ->orderBy('id')
            ->get()
            ->each(function (object $policy): void {
                $condition = $this->conditionFieldsFromBounds(
                    minimumValue: $this->toNullableFloat($policy->minimum_value ?? null),
                    maximumValue: $this->toNullableFloat($policy->maximum_value ?? null),
                );

                if ($condition === null) {
                    return;
                }

                DB::table('automation_threshold_policies')
                    ->where('id', $policy->id)
                    ->update([
                        'condition_mode' => 'guided',
                        'guided_condition' => json_encode($condition['guided_condition'], JSON_THROW_ON_ERROR),
                        'condition_json_logic' => json_encode($condition['condition_json_logic'], JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('automation_threshold_policies', function (Blueprint $table): void {
            $table->dropColumn([
                'condition_mode',
                'guided_condition',
                'condition_json_logic',
            ]);
        });
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

    private function toNullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
};
