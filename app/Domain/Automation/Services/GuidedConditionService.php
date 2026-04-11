<?php

declare(strict_types=1);

namespace App\Domain\Automation\Services;

use InvalidArgumentException;

class GuidedConditionService
{
    /**
     * @var array<int, string>
     */
    private const array SUPPORTED_OPERATORS = ['>', '>=', '<', '<=', '==', '!=', 'between', 'outside_between'];

    /**
     * @return list<array{value: string, label: string}>
     */
    public function operatorOptions(): array
    {
        return [
            ['value' => '>', 'label' => 'Greater than'],
            ['value' => '>=', 'label' => 'Greater than or equal'],
            ['value' => '<', 'label' => 'Less than'],
            ['value' => '<=', 'label' => 'Less than or equal'],
            ['value' => '==', 'label' => 'Equal to'],
            ['value' => '!=', 'label' => 'Not equal to'],
            ['value' => 'between', 'label' => 'Between'],
            ['value' => 'outside_between', 'label' => 'Outside between'],
        ];
    }

    /**
     * @param  array<string, mixed>  $guided
     * @return array{
     *     left: string,
     *     operator: string,
     *     right: float,
     *     right_secondary?: float
     * }
     */
    public function normalize(array $guided, string $defaultLeft = 'trigger.value'): array
    {
        $left = in_array($guided['left'] ?? null, ['trigger.value', 'query.value'], true)
            ? (string) $guided['left']
            : $defaultLeft;
        $operator = in_array($guided['operator'] ?? null, self::SUPPORTED_OPERATORS, true)
            ? (string) $guided['operator']
            : '>';
        $right = $this->resolveNumericValue($guided['right'] ?? null, 'guided right operand');

        if (! $this->requiresSecondaryOperand($operator)) {
            return [
                'left' => $left,
                'operator' => $operator,
                'right' => $right,
            ];
        }

        $rightSecondary = $this->resolveNumericValue($guided['right_secondary'] ?? null, 'guided secondary operand');
        $minimum = min($right, $rightSecondary);
        $maximum = max($right, $rightSecondary);

        return [
            'left' => $left,
            'operator' => $operator,
            'right' => $minimum,
            'right_secondary' => $maximum,
        ];
    }

    /**
     * @param  array<string, mixed>  $guided
     * @return array<string, mixed>
     */
    public function compile(array $guided): array
    {
        $normalized = $this->normalize($guided);
        $left = ['var' => $normalized['left']];
        $right = $normalized['right'];

        return match ($normalized['operator']) {
            'between' => [
                'and' => [
                    ['>=' => [$left, $right]],
                    ['<=' => [$left, $this->resolveSecondaryOperand($normalized)]],
                ],
            ],
            'outside_between' => [
                'or' => [
                    ['<' => [$left, $right]],
                    ['>' => [$left, $this->resolveSecondaryOperand($normalized)]],
                ],
            ],
            default => [
                $normalized['operator'] => [$left, $right],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $guided
     */
    public function label(array $guided, ?string $unit = null): string
    {
        $normalized = $this->normalize($guided);
        $right = $this->formatValue($normalized['right'], $unit);

        return match ($normalized['operator']) {
            '>' => "Above {$right}",
            '>=' => "At or above {$right}",
            '<' => "Below {$right}",
            '<=' => "At or below {$right}",
            '==' => "Equal to {$right}",
            '!=' => "Not equal to {$right}",
            'between' => sprintf(
                'Between %s and %s',
                $right,
                $this->formatValue($this->resolveSecondaryOperand($normalized), $unit),
            ),
            'outside_between' => sprintf(
                'Outside %s and %s',
                $right,
                $this->formatValue($this->resolveSecondaryOperand($normalized), $unit),
            ),
            default => 'Custom rule',
        };
    }

    /**
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
    public function fromLegacyBounds(?float $minimumValue, ?float $maximumValue, string $left = 'trigger.value'): array
    {
        if ($minimumValue !== null && $maximumValue !== null) {
            $guided = $this->normalize([
                'left' => $left,
                'operator' => 'outside_between',
                'right' => $minimumValue,
                'right_secondary' => $maximumValue,
            ]);

            return [
                'condition_mode' => 'guided',
                'guided_condition' => $guided,
                'condition_json_logic' => $this->compile($guided),
            ];
        }

        if ($minimumValue !== null) {
            $guided = $this->normalize([
                'left' => $left,
                'operator' => '<',
                'right' => $minimumValue,
            ]);

            return [
                'condition_mode' => 'guided',
                'guided_condition' => $guided,
                'condition_json_logic' => $this->compile($guided),
            ];
        }

        if ($maximumValue !== null) {
            $guided = $this->normalize([
                'left' => $left,
                'operator' => '>',
                'right' => $maximumValue,
            ]);

            return [
                'condition_mode' => 'guided',
                'guided_condition' => $guided,
                'condition_json_logic' => $this->compile($guided),
            ];
        }

        throw new InvalidArgumentException('Legacy bounds require at least one threshold value.');
    }

    public function requiresSecondaryOperand(string $operator): bool
    {
        return in_array($operator, ['between', 'outside_between'], true);
    }

    /**
     * @param  array{left: string, operator: string, right: float, right_secondary?: float}  $normalized
     */
    private function resolveSecondaryOperand(array $normalized): float
    {
        if (! array_key_exists('right_secondary', $normalized)) {
            throw new InvalidArgumentException('The guided secondary operand must be numeric.');
        }

        return $normalized['right_secondary'];
    }

    private function resolveNumericValue(mixed $value, string $label): float
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("The {$label} must be numeric.");
        }

        return (float) $value;
    }

    private function formatValue(float $value, ?string $unit = null): string
    {
        $formatted = rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');

        if (! is_string($unit) || trim($unit) === '') {
            return $formatted;
        }

        $normalizedUnit = strtolower(trim($unit));
        $displayUnit = match ($normalizedUnit) {
            'celsius' => '°C',
            'percent' => '%',
            'volts' => 'V',
            'watts' => 'W',
            'seconds' => 's',
            default => trim($unit),
        };

        return $formatted.$displayUnit;
    }
}
