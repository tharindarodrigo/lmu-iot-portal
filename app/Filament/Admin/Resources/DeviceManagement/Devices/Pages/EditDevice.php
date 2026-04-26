<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceManagement\Devices\Pages;

use App\Domain\DeviceManagement\Models\Device;
use App\Filament\Actions\DeviceManagement\ReplicateDeviceActions;
use App\Filament\Admin\Resources\DeviceManagement\Devices\DeviceResource;
use App\Filament\Admin\Resources\DeviceManagement\Devices\Pages\Concerns\InteractsWithVirtualDeviceLinks;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDevice extends EditRecord
{
    use InteractsWithVirtualDeviceLinks;

    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ReplicateDeviceActions::make(),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->record instanceof Device
            ? $this->seedVirtualDeviceLinkFormData($data, $this->record)
            : $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareVirtualDeviceFormDataForPersistence($data, $this->record instanceof Device ? $this->record : null);
    }

    protected function afterSave(): void
    {
        if ($this->record instanceof Device) {
            $this->syncVirtualDeviceLinks($this->record);
        }
    }
}
