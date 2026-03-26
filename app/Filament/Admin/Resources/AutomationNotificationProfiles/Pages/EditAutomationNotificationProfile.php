<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AutomationNotificationProfiles\Pages;

use App\Domain\Automation\Models\AutomationNotificationProfile;
use App\Domain\Automation\Services\ThresholdPolicyWorkflowProjector;
use App\Filament\Admin\Resources\AutomationNotificationProfiles\AutomationNotificationProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAutomationNotificationProfile extends EditRecord
{
    protected static string $resource = AutomationNotificationProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $profile = $this->getRecord();

        if ($profile instanceof AutomationNotificationProfile) {
            app(ThresholdPolicyWorkflowProjector::class)->syncForNotificationProfile($profile);
        }
    }
}
