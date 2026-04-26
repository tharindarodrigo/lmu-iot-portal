<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceManagement\Devices\Pages\Concerns;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\VirtualDeviceLink;
use Illuminate\Validation\ValidationException;

trait InteractsWithVirtualDeviceLinks
{
    /**
     * @var array<int, array{id?: int|null, purpose: string, source_device_id: int|null}>
     */
    protected array $virtualDeviceLinkPayload = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function seedVirtualDeviceLinkFormData(array $data, Device $device): array
    {
        $data['virtual_device_links'] = $device->virtualDeviceLinks()
            ->get(['id', 'purpose', 'source_device_id'])
            ->map(fn (VirtualDeviceLink $link): array => [
                'id' => (int) $link->id,
                'purpose' => (string) $link->purpose,
                'source_device_id' => (int) $link->source_device_id,
            ])
            ->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareVirtualDeviceFormDataForPersistence(array $data, ?Device $device = null): array
    {
        $links = $this->normalizeVirtualDeviceLinks($data['virtual_device_links'] ?? []);

        unset($data['virtual_device_links']);

        $isVirtual = (bool) ($data['is_virtual'] ?? false);

        if ($isVirtual) {
            $data['parent_device_id'] = null;

            $organizationId = is_numeric($data['organization_id'] ?? $device?->organization_id)
                ? (int) ($data['organization_id'] ?? $device?->organization_id)
                : null;

            $this->validateVirtualDeviceLinks(
                $links,
                $organizationId,
                $device?->getKey(),
            );
        } else {
            $links = [];
        }

        $this->virtualDeviceLinkPayload = $links;

        return $data;
    }

    protected function syncVirtualDeviceLinks(Device $device): void
    {
        if (! $device->isVirtual()) {
            $device->virtualDeviceLinks()->delete();
            $this->virtualDeviceLinkPayload = [];

            return;
        }

        $existingLinks = $device->virtualDeviceLinks()->get()->keyBy('id');
        $retainedIds = [];

        foreach ($this->virtualDeviceLinkPayload as $index => $linkData) {
            $linkId = is_numeric($linkData['id'] ?? null)
                ? (int) $linkData['id']
                : null;

            $payload = [
                'source_device_id' => (int) $linkData['source_device_id'],
                'purpose' => (string) $linkData['purpose'],
                'sequence' => $index + 1,
                'metadata' => $existingLinks->get($linkId)?->getAttribute('metadata') ?? [],
            ];

            if ($linkId !== null && $existingLinks->has($linkId)) {
                $device->virtualDeviceLinks()->whereKey($linkId)->update($payload);
                $retainedIds[] = $linkId;

                continue;
            }

            $retainedIds[] = (int) $device->virtualDeviceLinks()->create($payload)->id;
        }

        $staleLinks = $device->virtualDeviceLinks();

        if ($retainedIds !== []) {
            $staleLinks->whereNotIn('id', $retainedIds);
        }

        $staleLinks->delete();
        $this->virtualDeviceLinkPayload = [];
    }

    /**
     * @param  array<int, array{id?: int|null, purpose: string, source_device_id: int|null}>  $links
     */
    private function validateVirtualDeviceLinks(array $links, ?int $organizationId, mixed $deviceId): void
    {
        $errors = [];

        if ($organizationId === null && $links !== []) {
            throw ValidationException::withMessages([
                'organization_id' => 'Select an organization before attaching virtual source devices.',
            ]);
        }

        $sourceDeviceIds = collect($links)
            ->pluck('source_device_id')
            ->filter(fn (mixed $sourceDeviceId): bool => is_int($sourceDeviceId))
            ->unique()
            ->values()
            ->all();

        $sourceDevices = Device::query()
            ->whereIn('id', $sourceDeviceIds)
            ->get(['id', 'organization_id', 'is_virtual'])
            ->keyBy('id');

        $seenCombinations = [];
        $resolvedDeviceId = is_numeric($deviceId) ? (int) $deviceId : null;

        foreach ($links as $index => $link) {
            $purpose = trim((string) $link['purpose']);
            $sourceDeviceId = $link['source_device_id'];

            if ($purpose === '') {
                $errors["virtual_device_links.{$index}.purpose"] = 'Purpose is required.';
            } elseif (! preg_match('/^[a-z0-9_-]+$/', $purpose)) {
                $errors["virtual_device_links.{$index}.purpose"] = 'Purpose may only contain lowercase letters, numbers, underscores, and hyphens.';
            }

            if (! is_int($sourceDeviceId)) {
                $errors["virtual_device_links.{$index}.source_device_id"] = 'Select a source device.';

                continue;
            }

            $combinationKey = "{$sourceDeviceId}:{$purpose}";

            if (isset($seenCombinations[$combinationKey])) {
                $errors["virtual_device_links.{$index}.source_device_id"] = 'Each source device + purpose combination may only be used once.';
            }

            $seenCombinations[$combinationKey] = true;

            $sourceDevice = $sourceDevices->get($sourceDeviceId);

            if (! $sourceDevice instanceof Device) {
                $errors["virtual_device_links.{$index}.source_device_id"] = 'The selected source device could not be found.';

                continue;
            }

            if ($resolvedDeviceId !== null && $sourceDevice->getKey() === $resolvedDeviceId) {
                $errors["virtual_device_links.{$index}.source_device_id"] = 'A virtual device cannot attach itself as a source device.';
            }

            if ($organizationId !== null && (int) $sourceDevice->organization_id !== $organizationId) {
                $errors["virtual_device_links.{$index}.source_device_id"] = 'Source devices must belong to the same organization as the virtual device.';
            }

            if ($sourceDevice->isVirtual()) {
                $errors["virtual_device_links.{$index}.source_device_id"] = 'Only physical devices can be attached as virtual device sources.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<int, array{id?: int|null, purpose: string, source_device_id: int|null}>
     */
    private function normalizeVirtualDeviceLinks(mixed $links): array
    {
        if (! is_array($links)) {
            return [];
        }

        return collect($links)
            ->filter(fn (mixed $link): bool => is_array($link))
            ->map(function (array $link): array {
                $purpose = trim((string) ($link['purpose'] ?? ''));
                $sourceDeviceId = $link['source_device_id'] ?? null;

                return [
                    'id' => is_numeric($link['id'] ?? null) ? (int) $link['id'] : null,
                    'purpose' => $purpose,
                    'source_device_id' => is_numeric($sourceDeviceId) ? (int) $sourceDeviceId : null,
                ];
            })
            ->filter(fn (array $link): bool => $link['purpose'] !== '' || $link['source_device_id'] !== null)
            ->values()
            ->all();
    }
}
