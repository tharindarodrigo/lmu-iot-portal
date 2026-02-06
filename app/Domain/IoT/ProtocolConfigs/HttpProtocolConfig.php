<?php

declare(strict_types=1);

namespace App\Domain\IoT\ProtocolConfigs;

use App\Domain\IoT\Contracts\ProtocolConfigInterface;
use App\Domain\IoT\Enums\HttpAuthType;

final readonly class HttpProtocolConfig implements ProtocolConfigInterface
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $baseUrl,
        public string $telemetryEndpoint = '/telemetry',
        public ?string $controlEndpoint = null,
        public string $method = 'POST',
        public array $headers = [],
        public HttpAuthType $authType = HttpAuthType::None,
        public ?string $authToken = null,
        public ?string $authUsername = null,
        public ?string $authPassword = null,
        public int $timeout = 30,
    ) {}

    public function validate(): bool
    {
        if (empty($this->baseUrl) || ! filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            return false;
        }

        if (! in_array($this->method, ['GET', 'POST', 'PUT', 'PATCH'], true)) {
            return false;
        }

        if ($this->timeout <= 0) {
            return false;
        }

        // Validate auth requirements
        if ($this->authType === HttpAuthType::Bearer && empty($this->authToken)) {
            return false;
        }

        if ($this->authType === HttpAuthType::Basic && (empty($this->authUsername) || empty($this->authPassword))) {
            return false;
        }

        return true;
    }

    public function getTelemetryTopicTemplate(): string
    {
        return rtrim($this->baseUrl, '/').'/'.$this->telemetryEndpoint;
    }

    public function getControlTopicTemplate(): ?string
    {
        if ($this->controlEndpoint === null) {
            return null;
        }

        return rtrim($this->baseUrl, '/').'/'.$this->controlEndpoint;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'telemetry_endpoint' => $this->telemetryEndpoint,
            'control_endpoint' => $this->controlEndpoint,
            'method' => $this->method,
            'headers' => $this->headers,
            'auth_type' => $this->authType->value,
            'auth_token' => $this->authToken,
            'auth_username' => $this->authUsername,
            'auth_password' => $this->authPassword,
            'timeout' => $this->timeout,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $baseUrl = self::stringValue($data, 'base_url', required: true);
        $telemetryEndpoint = self::stringValue($data, 'telemetry_endpoint', default: '/telemetry');
        $controlEndpoint = self::nullableStringValue($data, 'control_endpoint');
        $method = self::stringValue($data, 'method', default: 'POST');
        $headers = self::headersValue($data, 'headers');
        $authType = self::authTypeValue($data, 'auth_type');
        $authToken = self::nullableStringValue($data, 'auth_token');
        $authUsername = self::nullableStringValue($data, 'auth_username');
        $authPassword = self::nullableStringValue($data, 'auth_password');
        $timeout = self::intValue($data, 'timeout', default: 30);

        return new self(
            baseUrl: $baseUrl,
            telemetryEndpoint: $telemetryEndpoint,
            controlEndpoint: $controlEndpoint,
            method: $method,
            headers: $headers,
            authType: $authType,
            authToken: $authToken,
            authUsername: $authUsername,
            authPassword: $authPassword,
            timeout: $timeout,
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
     * @return array<string, string>
     */
    private static function headersValue(array $data, string $key): array
    {
        if (! array_key_exists($key, $data) || ! is_array($data[$key])) {
            return [];
        }

        $headers = [];

        foreach ($data[$key] as $headerKey => $headerValue) {
            $normalizedKey = self::toString($headerKey);
            $normalizedValue = self::toString($headerValue);

            if ($normalizedKey === null || $normalizedValue === null) {
                continue;
            }

            $headers[$normalizedKey] = $normalizedValue;
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function authTypeValue(array $data, string $key): HttpAuthType
    {
        if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            return HttpAuthType::None;
        }

        $value = $data[$key];

        if (! is_string($value) && ! is_int($value)) {
            return HttpAuthType::None;
        }

        return HttpAuthType::from($value);
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
