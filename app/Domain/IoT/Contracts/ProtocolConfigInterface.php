<?php

declare(strict_types=1);

namespace App\Domain\IoT\Contracts;

interface ProtocolConfigInterface
{
    /**
     * Validate the protocol configuration.
     */
    public function validate(): bool;

    /**
     * Get the base MQTT topic prefix for this protocol.
     *
     * Returns null for protocols that don't use topics (e.g. HTTP).
     */
    public function getBaseTopic(): ?string;

    /**
     * Convert the configuration to an array for storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Create an instance from an array (deserialization).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static;
}
