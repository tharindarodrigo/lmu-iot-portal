<?php

namespace App\Filament\Portal\Resources\DeviceManagement\Devices\Pages;

use App\Filament\Portal\Resources\DeviceManagement\Devices\DeviceResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateDevice extends CreateRecord
{
    protected static string $resource = DeviceResource::class;

    public function getMaxContentWidth(): string
    {
        return 'full';
    }
}
