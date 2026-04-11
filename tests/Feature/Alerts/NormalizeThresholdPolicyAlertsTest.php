<?php

declare(strict_types=1);

use App\Domain\Alerts\Actions\NormalizeThresholdPolicyAlerts;
use App\Domain\Alerts\Models\Alert;
use App\Domain\Alerts\Models\ThresholdPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('normalizes the open alert for a threshold policy', function (): void {
    $policy = ThresholdPolicy::factory()->create();
    $alert = Alert::factory()->create([
        'threshold_policy_id' => $policy->id,
    ]);

    $normalizedAlert = app(NormalizeThresholdPolicyAlerts::class)($policy);

    expect($normalizedAlert)->toBeInstanceOf(Alert::class)
        ->and($normalizedAlert?->isOpen())->toBeFalse();

    $alert->refresh();

    expect($alert->normalized_at)->not->toBeNull();
});

it('returns null when the threshold policy has no open alert', function (): void {
    $policy = ThresholdPolicy::factory()->create();

    $normalizedAlert = app(NormalizeThresholdPolicyAlerts::class)($policy);

    expect($normalizedAlert)->toBeNull();
});
