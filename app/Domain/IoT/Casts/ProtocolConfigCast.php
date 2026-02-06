<?php

declare(strict_types=1);

namespace App\Domain\IoT\Casts;

use App\Domain\IoT\Contracts\ProtocolConfigInterface;
use App\Domain\IoT\Enums\ProtocolType;
use App\Domain\IoT\ProtocolConfigs\HttpProtocolConfig;
use App\Domain\IoT\ProtocolConfigs\MqttProtocolConfig;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<ProtocolConfigInterface|null, mixed>
 */
class ProtocolConfigCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?ProtocolConfigInterface
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $data = $this->normalizeData($value);
        } elseif (is_string($value)) {
            $decoded = json_decode($value, true);

            if (! is_array($decoded)) {
                return null;
            }

            $data = $this->normalizeData($decoded);
        } else {
            return null;
        }

        $protocol = $attributes['default_protocol'] ?? null;

        if ($protocol === null) {
            throw new InvalidArgumentException('default_protocol attribute is required for ProtocolConfigCast');
        }

        if (! $protocol instanceof ProtocolType) {
            if (! is_string($protocol) && ! is_int($protocol)) {
                throw new InvalidArgumentException('default_protocol must be a ProtocolType, string, or int');
            }

            $protocol = ProtocolType::from($protocol);
        }

        return match ($protocol) {
            ProtocolType::Mqtt => MqttProtocolConfig::fromArray($data),
            ProtocolType::Http => HttpProtocolConfig::fromArray($data),
        };
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value === null) {
            return $this->encode([]);
        }

        if (is_array($value)) {
            return $this->encode($this->normalizeData($value));
        }

        if ($value instanceof ProtocolConfigInterface) {
            return $this->encode($value->toArray());
        }

        throw new InvalidArgumentException('Value must be an instance of ProtocolConfigInterface or an array');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload): string
    {
        $encoded = json_encode($payload);

        if ($encoded === false) {
            throw new InvalidArgumentException('Failed to encode protocol configuration.');
        }

        return $encoded;
    }

    /**
     * @param  array<mixed, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
