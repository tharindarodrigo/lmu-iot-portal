<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AutomationNotificationProfiles\Pages;

use App\Domain\Automation\Models\AutomationNotificationProfile;
use App\Domain\Automation\Services\ThresholdPolicyWorkflowProjector;
use App\Filament\Admin\Resources\AutomationNotificationProfiles\AutomationNotificationProfileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAutomationNotificationProfile extends CreateRecord
{
    protected static string $resource = AutomationNotificationProfileResource::class;

    protected function afterCreate(): void
    {
        $profile = $this->getRecord();

        if ($profile instanceof AutomationNotificationProfile) {
            app(ThresholdPolicyWorkflowProjector::class)->syncForNotificationProfile($profile);
        }
    }
}
