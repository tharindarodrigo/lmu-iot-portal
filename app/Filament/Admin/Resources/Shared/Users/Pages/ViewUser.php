<?php

namespace App\Filament\Admin\Resources\Shared\Users\Pages;

use App\Filament\Admin\Resources\Shared\Users\UserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
