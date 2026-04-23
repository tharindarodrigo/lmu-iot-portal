<?php

namespace App\Filament\Admin\Resources\Shared\Entities\Pages;

use App\Filament\Admin\Resources\Shared\Entities\EntityResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEntity extends ViewRecord
{
    protected static string $resource = EntityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
