<?php

use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\Shared\Organizations\Pages\CreateOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->user);
});

it('can render the create organization page', function (): void {
    livewire(CreateOrganization::class)
        ->assertSuccessful();
});

it('can create a new organization', function (): void {
    livewire(CreateOrganization::class)
        ->fillForm([
            'name' => 'New Organization',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('organizations', [
        'name' => 'New Organization',
    ]);
});

it('validates required name field', function (): void {
    livewire(CreateOrganization::class)
        ->fillForm([
            'name' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('generates slug from name', function (): void {
    livewire(CreateOrganization::class)
        ->fillForm([
            'name' => 'New Test Organization',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $organization = Organization::where('name', 'New Test Organization')->first();
    $this->assertNotNull($organization);
    $this->assertNotNull($organization->slug);
});
