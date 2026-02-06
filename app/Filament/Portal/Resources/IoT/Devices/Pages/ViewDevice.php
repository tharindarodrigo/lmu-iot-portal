<?php

namespace App\Filament\Portal\Resources\IoT\Devices\Pages;

use App\Filament\Portal\Resources\IoT\Devices\DeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDevice extends ViewRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
