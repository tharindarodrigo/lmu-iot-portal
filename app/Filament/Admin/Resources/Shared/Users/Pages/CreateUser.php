<?php

namespace App\Filament\Admin\Resources\Shared\Users\Pages;

use App\Filament\Admin\Resources\Shared\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
