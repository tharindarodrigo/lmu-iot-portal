<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IoT\DeviceTelemetryLogs\Pages;

use App\Filament\Admin\Resources\IoT\DeviceTelemetryLogs\DeviceTelemetryLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDeviceTelemetryLog extends ViewRecord
{
    protected static string $resource = DeviceTelemetryLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
