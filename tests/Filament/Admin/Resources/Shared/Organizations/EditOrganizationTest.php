<?php

use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\Shared\Organizations\Pages\EditOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->user);
});

it('can render the edit organization page', function (): void {
    $organization = Organization::factory()->create();

    livewire(EditOrganization::class, ['record' => $organization->getRouteKey()])
        ->assertSuccessful();
});

it('can update an organization', function (): void {
    $organization = Organization::factory()->create([
        'name' => 'Original Name',
    ]);

    livewire(EditOrganization::class, ['record' => $organization->getRouteKey()])
        ->fillForm([
            'name' => 'Updated Name',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('organizations', [
        'id' => $organization->id,
        'name' => 'Updated Name',
    ]);
});

it('validates required name field', function (): void {
    $organization = Organization::factory()->create();

    livewire(EditOrganization::class, ['record' => $organization->getRouteKey()])
        ->fillForm([
            'name' => '',
        ])
        ->call('save')
        ->assertHasFormErrors(['name' => 'required']);
});

it('populates form with existing data', function (): void {
    $organization = Organization::factory()->create([
        'name' => 'Test Organization',
    ]);

    livewire(EditOrganization::class, ['record' => $organization->getRouteKey()])
        ->assertFormSet([
            'name' => 'Test Organization',
        ]);
});
