<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\ValueObjects\VirtualStandards;

use Illuminate\Support\Str;

final readonly class VirtualStandardSource
{
    /**
     * @param  array<int, string>  $allowedDeviceTypeKeys
     */
    public function __construct(
        public string $purpose,
        public string $label,
        public bool $required,
        public array $allowedDeviceTypeKeys = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string $purpose, array $data): self
    {
        $allowedDeviceTypeKeys = [];
        $allowedDeviceTypeKeyCandidates = $data['allowed_device_type_keys'] ?? null;

        if (is_array($allowedDeviceTypeKeyCandidates)) {
            foreach ($allowedDeviceTypeKeyCandidates as $allowedDeviceTypeKeyCandidate) {
                if (! is_string($allowedDeviceTypeKeyCandidate) || trim($allowedDeviceTypeKeyCandidate) === '') {
                    continue;
                }

                $allowedDeviceTypeKeys[] = trim($allowedDeviceTypeKeyCandidate);
            }
        }

        return new self(
            purpose: $purpose,
            label: is_string($data['label'] ?? null) && trim((string) $data['label']) !== ''
                ? trim((string) $data['label'])
                : Str::headline($purpose),
            required: (bool) ($data['required'] ?? false),
            allowedDeviceTypeKeys: $allowedDeviceTypeKeys,
        );
    }

    /**
     * @return array{label: string, required: bool, allowed_device_type_keys: array<int, string>}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'required' => $this->required,
            'allowed_device_type_keys' => $this->allowedDeviceTypeKeys,
        ];
    }

    public function allowsDeviceTypeKey(?string $deviceTypeKey): bool
    {
        if ($this->allowedDeviceTypeKeys === []) {
            return true;
        }

        return is_string($deviceTypeKey) && in_array($deviceTypeKey, $this->allowedDeviceTypeKeys, true);
    }
}
