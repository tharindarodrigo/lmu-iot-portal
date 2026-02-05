<?php

namespace App\Filament\Admin\Resources\Authorization\Permissions\Pages;

use App\Filament\Admin\Resources\Authorization\Permissions\PermissionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePermission extends CreateRecord
{
    protected static string $resource = PermissionResource::class;
}
