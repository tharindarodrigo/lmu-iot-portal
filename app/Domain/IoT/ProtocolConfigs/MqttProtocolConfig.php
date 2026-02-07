<?php

declare(strict_types=1);

namespace App\Domain\IoT\ProtocolConfigs;

use App\Domain\IoT\Contracts\ProtocolConfigInterface;

final readonly class MqttProtocolConfig implements ProtocolConfigInterface
{
    public function __construct(
        public string $brokerHost,
        public int $brokerPort = 1883,
        public ?string $username = null,
        public ?string $password = null,
        public bool $useTls = false,
        public string $baseTopic = 'device',
    ) {}

    public function validate(): bool
    {
        return ! empty($this->brokerHost)
            && $this->brokerPort > 0
            && $this->brokerPort <= 65535;
    }

    public function getBaseTopic(): string
    {
        return $this->baseTopic;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'broker_host' => $this->brokerHost,
            'broker_port' => $this->brokerPort,
            'username' => $this->username,
            'password' => $this->password,
            'use_tls' => $this->useTls,
            'base_topic' => $this->baseTopic,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $brokerHost = self::stringValue($data, 'broker_host', required: true);
        $brokerPort = self::intValue($data, 'broker_port', default: 1883);
        $username = self::nullableStringValue($data, 'username');
        $password = self::nullableStringValue($data, 'password');
        $useTls = self::boolValue($data, 'use_tls', default: false);
        $baseTopic = self::stringValue($data, 'base_topic', default: 'device');

        return new self(
            brokerHost: $brokerHost,
            brokerPort: $brokerPort,
            username: $username,
            password: $password,
            useTls: $useTls,
            baseTopic: $baseTopic,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function stringValue(array $data, string $key, ?string $default = null, bool $required = false): string
    {
        if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            if ($required) {
                throw new \InvalidArgumentException(sprintf('%s is required', $key));
            }

            return $default ?? '';
        }

        $value = self::toString($data[$key]);

        if ($value === null) {
            return $default ?? '';
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function nullableStringValue(array $data, string $key, ?string $default = null): ?string
    {
        if (! array_key_exists($key, $data)) {
            return $default;
        }

        if ($data[$key] === null || $data[$key] === '') {
            return null;
        }

        return self::toString($data[$key]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function intValue(array $data, string $key, int $default = 0): int
    {
        if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            return $default;
        }

        $value = $data[$key];

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_bool($value)) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function boolValue(array $data, string $key, bool $default = false): bool
    {
        if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            return $default;
        }

        $value = $data[$key];

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $normalized = strtolower($value);

            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return $default;
    }

    private static function toString(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return null;
    }
}
