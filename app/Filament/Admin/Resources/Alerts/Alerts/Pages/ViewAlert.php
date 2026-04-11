<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Alerts\Alerts\Pages;

use App\Domain\Alerts\Models\Alert;
use App\Filament\Admin\Resources\Alerts\Alerts\AlertResource;
use App\Filament\Admin\Resources\AutomationThresholdPolicies\AutomationThresholdPolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewAlert extends ViewRecord
{
    protected static string $resource = AlertResource::class;

    protected function getHeaderActions(): array
    {
        $alert = $this->getRecord();

        return [
            Actions\Action::make('thresholdPolicy')
                ->label('Threshold Policy')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->url(function () use ($alert): ?string {
                    if (! $alert instanceof Alert || $alert->thresholdPolicy === null) {
                        return null;
                    }

                    return AutomationThresholdPolicyResource::getUrl('edit', ['record' => $alert->thresholdPolicy]);
                })
                ->visible(fn (): bool => $alert instanceof Alert && $alert->thresholdPolicy !== null),
        ];
    }
}
