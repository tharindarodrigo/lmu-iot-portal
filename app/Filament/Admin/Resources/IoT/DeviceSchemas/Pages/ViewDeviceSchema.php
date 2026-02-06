<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IoT\DeviceSchemas\Pages;

use App\Filament\Admin\Resources\IoT\DeviceSchemas\DeviceSchemaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDeviceSchema extends ViewRecord
{
    protected static string $resource = DeviceSchemaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
