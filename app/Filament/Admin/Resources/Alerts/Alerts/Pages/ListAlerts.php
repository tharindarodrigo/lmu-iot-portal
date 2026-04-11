<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Alerts\Alerts\Pages;

use App\Filament\Admin\Resources\Alerts\Alerts\AlertResource;
use Filament\Resources\Pages\ListRecords;

class ListAlerts extends ListRecords
{
    protected static string $resource = AlertResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
