<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceManagement\Devices\Pages;

use App\Domain\DeviceManagement\Models\Device;
use App\Filament\Admin\Resources\DeviceManagement\Devices\DeviceResource;
use App\Filament\Admin\Resources\DeviceManagement\Devices\Pages\Concerns\InteractsWithVirtualDeviceLinks;
use Filament\Resources\Pages\CreateRecord;

class CreateDevice extends CreateRecord
{
    use InteractsWithVirtualDeviceLinks;

    protected static string $resource = DeviceResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareVirtualDeviceFormDataForPersistence($data);
    }

    protected function afterCreate(): void
    {
        if ($this->record instanceof Device) {
            $this->syncVirtualDeviceLinks($this->record);
        }
    }
}
