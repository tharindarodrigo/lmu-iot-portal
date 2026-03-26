<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Automation\AutomationWorkflows\Pages;

use App\Domain\Automation\Models\AutomationWorkflow;
use App\Filament\Admin\Resources\Automation\AutomationWorkflows\AutomationWorkflowResource;
use App\Filament\Admin\Resources\AutomationThresholdPolicies\AutomationThresholdPolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class EditAutomationWorkflow extends EditRecord
{
    protected static string $resource = AutomationWorkflowResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();
        $actions = [
            Actions\ViewAction::make(),
        ];

        if ($record instanceof AutomationWorkflow && $record->isManagedBy('threshold_policy')) {
            $thresholdPolicyId = data_get($record->managed_metadata, 'threshold_policy_id');

            if (is_numeric($thresholdPolicyId)) {
                $actions[] = Actions\Action::make('thresholdPolicy')
                    ->label('Threshold Policy')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->url(AutomationThresholdPolicyResource::getUrl('edit', ['record' => (int) $thresholdPolicyId]));
            }

            return $actions;
        }

        $actions[] = Actions\Action::make('dagEditor')
            ->label('DAG Editor')
            ->icon(Heroicon::OutlinedSquare3Stack3d)
            ->url(fn (): string => AutomationWorkflowResource::getUrl('dag-editor', ['record' => $this->getRecord()]));
        $actions[] = Actions\DeleteAction::make();

        return $actions;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $slugSource = trim($this->resolveStringValue($data['slug'] ?? null));
        if ($slugSource === '') {
            $slugSource = $this->resolveStringValue($data['name'] ?? null);
        }

        $data['slug'] = Str::slug($slugSource);
        $data['updated_by'] = auth()->id();

        return $data;
    }

    private function resolveStringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return '';
    }
}
