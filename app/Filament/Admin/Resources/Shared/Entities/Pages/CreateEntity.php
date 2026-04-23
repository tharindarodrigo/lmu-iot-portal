<?php

namespace App\Filament\Admin\Resources\Shared\Entities\Pages;

use App\Filament\Admin\Resources\Shared\Entities\EntityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEntity extends CreateRecord
{
    protected static string $resource = EntityResource::class;
}
