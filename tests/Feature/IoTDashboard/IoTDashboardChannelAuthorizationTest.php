<?php

declare(strict_types=1);

use App\Broadcasting\IoTDashboardOrganizationChannel;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('authorizes private organization telemetry channels for organization members', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['is_super_admin' => false]);
    $user->organizations()->attach($organization->id);

    $channel = new IoTDashboardOrganizationChannel;

    expect($channel->join($user, $organization->id))->toBeTrue();
});

it('denies private organization telemetry channels for non-members', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['is_super_admin' => false]);

    $channel = new IoTDashboardOrganizationChannel;

    expect($channel->join($user, $organization->id))->toBeFalse();
});
