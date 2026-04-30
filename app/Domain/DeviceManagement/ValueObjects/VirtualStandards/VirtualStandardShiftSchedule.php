<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\ValueObjects\VirtualStandards;

final readonly class VirtualStandardShiftSchedule
{
    public function __construct(
        public string $id,
        public string $label,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $id = is_string($data['id'] ?? null) ? trim($data['id']) : '';
        $label = is_string($data['label'] ?? null) ? trim($data['label']) : '';

        if ($id === '' && $label === '') {
            return null;
        }

        return new self(
            id: $id,
            label: $label,
        );
    }

    /**
     * @return array{id: string, label: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
        ];
    }
}
