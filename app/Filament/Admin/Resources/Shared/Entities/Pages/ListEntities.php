<?php

namespace App\Filament\Admin\Resources\Shared\Entities\Pages;

use App\Filament\Admin\Resources\Shared\Entities\EntityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEntities extends ListRecords
{
    protected static string $resource = EntityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
