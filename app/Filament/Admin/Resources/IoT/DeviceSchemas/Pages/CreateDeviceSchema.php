<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IoT\DeviceSchemas\Pages;

use App\Filament\Admin\Resources\IoT\DeviceSchemas\DeviceSchemaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDeviceSchema extends CreateRecord
{
    protected static string $resource = DeviceSchemaResource::class;
}
