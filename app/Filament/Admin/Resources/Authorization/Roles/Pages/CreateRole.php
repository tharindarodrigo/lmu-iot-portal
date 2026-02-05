<?php

namespace App\Filament\Admin\Resources\Authorization\Roles\Pages;

use App\Filament\Admin\Resources\Authorization\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;
}
