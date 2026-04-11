<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AutomationNotificationProfiles\Pages;

use App\Filament\Admin\Resources\AutomationNotificationProfiles\AutomationNotificationProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAutomationNotificationProfiles extends ListRecords
{
    protected static string $resource = AutomationNotificationProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
