<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Widgets\ThresholdStatusGrid;

use App\Domain\IoTDashboard\Contracts\WidgetConfig;
use App\Domain\IoTDashboard\Enums\WidgetType;
use App\Domain\IoTDashboard\Widgets\Concerns\NormalizesWidgetConfig;

final class ThresholdStatusGridConfig implements WidgetConfig
{
    use NormalizesWidgetConfig;

    /**
     * @param  array<int, int>  $policyIds
     * @param  list<array{
     *     device_id: int,
     *     label: string|null,
     *     parameter_key: string,
     *     minimum_value: float|null,
     *     maximum_value: float|null
     * }>  $deviceCards
     */
    private function __construct(
        private readonly string $scope,
        private readonly array $policyIds,
        private readonly string $displayMode,
        private readonly array $deviceCards,
        private readonly bool $useWebsocket,
        private readonly bool $usePolling,
        private readonly int $pollingIntervalSeconds,
        private readonly int $lookbackMinutes,
        private readonly int $maxPoints,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): static
    {
        $transport = is_array($config['transport'] ?? null)
            ? $config['transport']
            : [];
        $window = is_array($config['window'] ?? null)
            ? $config['window']
            : [];
        $deviceCards = self::normalizeDeviceCards($config['device_cards'] ?? []);

        return new self(
            scope: in_array($config['scope'] ?? null, ['all_active', 'selected', 'device_cards'], true)
                ? (string) $config['scope']
                : ($deviceCards !== [] ? 'device_cards' : 'all_active'),
            policyIds: self::normalizePolicyIds($config['policy_ids'] ?? []),
            displayMode: self::normalizeDisplayMode($config['display_mode'] ?? null),
            deviceCards: $deviceCards,
            useWebsocket: false,
            usePolling: self::normalizeBool(
                $transport['use_polling'] ?? $config['use_polling'] ?? true,
                true,
            ),
            pollingIntervalSeconds: self::normalizeInt(
                $transport['polling_interval_seconds'] ?? $config['polling_interval_seconds'] ?? 15,
                15,
                2,
                300,
            ),
            lookbackMinutes: self::normalizeInt(
                $window['lookback_minutes'] ?? $config['lookback_minutes'] ?? 180,
                180,
                1,
                129600,
            ),
            maxPoints: self::normalizeInt(
                $window['max_points'] ?? $config['max_points'] ?? 1,
                1,
                1,
                25,
            ),
        );
    }

    public function type(): WidgetType
    {
        return WidgetType::ThresholdStatusGrid;
    }

    public function scope(): string
    {
        return $this->scope;
    }

    /**
     * @return array<int, int>
     */
    public function policyIds(): array
    {
        return $this->policyIds;
    }

    public function displayMode(): string
    {
        return $this->displayMode;
    }

    /**
     * @return list<array{
     *     device_id: int,
     *     label: string|null,
     *     parameter_key: string,
     *     minimum_value: float|null,
     *     maximum_value: float|null
     * }>
     */
    public function deviceCards(): array
    {
        return $this->deviceCards;
    }

    public function series(): array
    {
        return [];
    }

    public function useWebsocket(): bool
    {
        return $this->useWebsocket;
    }

    public function usePolling(): bool
    {
        return $this->usePolling;
    }

    public function pollingIntervalSeconds(): int
    {
        return $this->pollingIntervalSeconds;
    }

    public function lookbackMinutes(): int
    {
        return $this->lookbackMinutes;
    }

    public function maxPoints(): int
    {
        return $this->maxPoints;
    }

    public function toArray(): array
    {
        return [
            'scope' => $this->scope,
            'policy_ids' => $this->policyIds,
            'display_mode' => $this->displayMode,
            'device_cards' => $this->deviceCards,
            'transport' => [
                'use_websocket' => false,
                'use_polling' => $this->usePolling,
                'polling_interval_seconds' => $this->pollingIntervalSeconds,
            ],
            'window' => [
                'lookback_minutes' => $this->lookbackMinutes,
                'max_points' => $this->maxPoints,
            ],
        ];
    }

    public function meta(): array
    {
        return [
            'scope' => $this->scope,
            'selected_policy_count' => count($this->policyIds),
            'display_mode' => $this->displayMode,
            'configured_device_count' => count($this->deviceCards),
        ];
    }

    /**
     * @param  array<int, mixed>|mixed  $policyIds
     * @return array<int, int>
     */
    private static function normalizePolicyIds(mixed $policyIds): array
    {
        if (! is_array($policyIds)) {
            return [];
        }

        $normalizedPolicyIds = [];

        foreach ($policyIds as $policyId) {
            if (! is_numeric($policyId)) {
                continue;
            }

            $resolvedPolicyId = (int) $policyId;

            if ($resolvedPolicyId < 1) {
                continue;
            }

            $normalizedPolicyIds[$resolvedPolicyId] = $resolvedPolicyId;
        }

        return array_values($normalizedPolicyIds);
    }

    private static function normalizeDisplayMode(mixed $displayMode): string
    {
        return in_array($displayMode, ['standard', 'sri_lankan_temperature'], true)
            ? (string) $displayMode
            : 'standard';
    }

    /**
     * @param  array<int, mixed>|mixed  $deviceCards
     * @return list<array{
     *     device_id: int,
     *     label: string|null,
     *     parameter_key: string,
     *     minimum_value: float|null,
     *     maximum_value: float|null
     * }>
     */
    private static function normalizeDeviceCards(mixed $deviceCards): array
    {
        if (! is_array($deviceCards)) {
            return [];
        }

        $normalizedCards = [];
        $seenKeys = [];

        foreach (array_values($deviceCards) as $deviceCard) {
            if (! is_array($deviceCard)) {
                continue;
            }

            $deviceId = is_numeric($deviceCard['device_id'] ?? null)
                ? (int) $deviceCard['device_id']
                : 0;
            $parameterKey = is_string($deviceCard['parameter_key'] ?? null)
                ? trim((string) $deviceCard['parameter_key'])
                : '';

            if ($deviceId < 1 || $parameterKey === '') {
                continue;
            }

            $cardKey = "{$deviceId}:{$parameterKey}";

            if (in_array($cardKey, $seenKeys, true)) {
                continue;
            }

            $seenKeys[] = $cardKey;
            $label = is_string($deviceCard['label'] ?? null) && trim((string) $deviceCard['label']) !== ''
                ? trim((string) $deviceCard['label'])
                : null;

            $normalizedCards[] = [
                'device_id' => $deviceId,
                'label' => $label,
                'parameter_key' => $parameterKey,
                'minimum_value' => self::normalizeNullableFloat($deviceCard['minimum_value'] ?? null),
                'maximum_value' => self::normalizeNullableFloat($deviceCard['maximum_value'] ?? null),
            ];
        }

        return $normalizedCards;
    }

    private static function normalizeNullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
