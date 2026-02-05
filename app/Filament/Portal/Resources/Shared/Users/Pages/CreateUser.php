<?php

namespace App\Filament\Portal\Resources\Shared\Users\Pages;

use App\Filament\Portal\Resources\Shared\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
