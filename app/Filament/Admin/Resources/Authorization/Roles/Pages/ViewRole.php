<?php

namespace App\Filament\Admin\Resources\Authorization\Roles\Pages;

use App\Filament\Admin\Resources\Authorization\Roles\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewRole extends ViewRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
