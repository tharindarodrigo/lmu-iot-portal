<?php

namespace App\Filament\Portal\Resources\DeviceManagement\Devices\Pages;

use App\Filament\Portal\Resources\DeviceManagement\Devices\DeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditDevice extends EditRecord
{
    protected static string $resource = DeviceResource::class;

    public function getMaxContentWidth(): string
    {
        return 'full';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
