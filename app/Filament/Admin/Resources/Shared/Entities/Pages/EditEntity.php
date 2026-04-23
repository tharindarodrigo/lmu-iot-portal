<?php

namespace App\Filament\Admin\Resources\Shared\Entities\Pages;

use App\Filament\Admin\Resources\Shared\Entities\EntityResource;
use Filament\Resources\Pages\EditRecord;

class EditEntity extends EditRecord
{
    protected static string $resource = EntityResource::class;
}
