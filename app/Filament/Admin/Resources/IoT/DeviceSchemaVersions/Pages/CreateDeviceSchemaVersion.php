<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IoT\DeviceSchemaVersions\Pages;

use App\Filament\Admin\Resources\IoT\DeviceSchemaVersions\DeviceSchemaVersionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDeviceSchemaVersion extends CreateRecord
{
    protected static string $resource = DeviceSchemaVersionResource::class;
}
