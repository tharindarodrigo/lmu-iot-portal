<?php

namespace App\Filament\Admin\Resources\AutomationNotificationProfiles\Pages;

use App\Filament\Admin\Resources\AutomationNotificationProfiles\AutomationNotificationProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAutomationNotificationProfile extends ViewRecord
{
    protected static string $resource = AutomationNotificationProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
