<?php

namespace App\Filament\Admin\Resources\AutomationThresholdPolicies\Pages;

use App\Filament\Admin\Resources\AutomationThresholdPolicies\AutomationThresholdPolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAutomationThresholdPolicy extends ViewRecord
{
    protected static string $resource = AutomationThresholdPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
