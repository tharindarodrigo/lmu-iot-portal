<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AutomationThresholdPolicies\Pages;

use App\Domain\Automation\Models\AutomationThresholdPolicy;
use App\Domain\Automation\Services\ThresholdPolicyWorkflowProjector;
use App\Filament\Admin\Resources\AutomationThresholdPolicies\AutomationThresholdPolicyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAutomationThresholdPolicy extends CreateRecord
{
    protected static string $resource = AutomationThresholdPolicyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return AutomationThresholdPolicyResource::prepareThresholdPolicyFormData($data);
    }

    protected function afterCreate(): void
    {
        $policy = $this->getRecord();

        if ($policy instanceof AutomationThresholdPolicy) {
            app(ThresholdPolicyWorkflowProjector::class)->sync($policy);
        }
    }
}
