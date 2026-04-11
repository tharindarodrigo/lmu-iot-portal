<?php

declare(strict_types=1);

use App\Domain\Alerts\Models\Alert;
use App\Domain\Alerts\Models\ThresholdPolicy;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\Alerts\Alerts\Pages\ListAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($admin);
});

it('renders the alerts list page', function (): void {
    livewire(ListAlerts::class)
        ->assertSuccessful();
});

it('shows persisted alerts in the table', function (): void {
    $openAlert = Alert::factory()->create();
    $normalizedAlert = Alert::factory()->normalized()->create();

    livewire(ListAlerts::class)
        ->assertCanSeeTableRecords([$openAlert, $normalizedAlert]);
});

it('can filter alerts by status', function (): void {
    $openAlert = Alert::factory()->create();
    $normalizedAlert = Alert::factory()->normalized()->create();

    livewire(ListAlerts::class)
        ->filterTable('status', 'open')
        ->assertCanSeeTableRecords([$openAlert])
        ->assertCanNotSeeTableRecords([$normalizedAlert]);
});

it('can filter alerts by threshold policy', function (): void {
    $policyA = ThresholdPolicy::factory()->create(['name' => 'Cold Room 1']);
    $policyB = ThresholdPolicy::factory()->create(['name' => 'Cold Room 2']);
    $alertA = Alert::factory()->create(['threshold_policy_id' => $policyA->id]);
    $alertB = Alert::factory()->create(['threshold_policy_id' => $policyB->id]);

    livewire(ListAlerts::class)
        ->filterTable('thresholdPolicy', $policyB->id)
        ->assertCanSeeTableRecords([$alertB])
        ->assertCanNotSeeTableRecords([$alertA]);
});
