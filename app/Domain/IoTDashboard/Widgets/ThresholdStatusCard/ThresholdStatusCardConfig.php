<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Widgets\ThresholdStatusCard;

use App\Domain\IoTDashboard\Contracts\WidgetConfig;
use App\Domain\IoTDashboard\Enums\WidgetType;
use App\Domain\IoTDashboard\Widgets\Concerns\NormalizesWidgetConfig;

final class ThresholdStatusCardConfig implements WidgetConfig
{
    use NormalizesWidgetConfig;

    private function __construct(
        private readonly int $policyId,
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
        $transport = is_array($config['transport'] ?? null) ? $config['transport'] : [];
        $window = is_array($config['window'] ?? null) ? $config['window'] : [];

        return new self(
            policyId: self::normalizeInt($config['policy_id'] ?? null, 0, 0, PHP_INT_MAX),
            useWebsocket: false,
            usePolling: self::normalizeBool($transport['use_polling'] ?? $config['use_polling'] ?? true, true),
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
        return WidgetType::ThresholdStatusCard;
    }

    public function policyId(): int
    {
        return $this->policyId;
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
            'policy_id' => $this->policyId,
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
            'policy_id' => $this->policyId,
        ];
    }
}
