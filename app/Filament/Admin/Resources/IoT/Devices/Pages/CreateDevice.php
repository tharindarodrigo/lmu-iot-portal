<?php

namespace App\Filament\Admin\Resources\IoT\Devices\Pages;

use App\Filament\Admin\Resources\IoT\Devices\DeviceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDevice extends CreateRecord
{
    protected static string $resource = DeviceResource::class;
}
