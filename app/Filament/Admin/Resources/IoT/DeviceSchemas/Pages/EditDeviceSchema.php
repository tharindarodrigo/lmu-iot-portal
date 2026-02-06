<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IoT\DeviceSchemas\Pages;

use App\Filament\Admin\Resources\IoT\DeviceSchemas\DeviceSchemaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeviceSchema extends EditRecord
{
    protected static string $resource = DeviceSchemaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
