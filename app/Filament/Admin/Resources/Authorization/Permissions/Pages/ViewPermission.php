<?php

namespace App\Filament\Admin\Resources\Authorization\Permissions\Pages;

use App\Filament\Admin\Resources\Authorization\Permissions\PermissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPermission extends ViewRecord
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Permissions are typically not editable after creation
            // but you can add Actions\EditAction::make() if needed
        ];
    }
}
