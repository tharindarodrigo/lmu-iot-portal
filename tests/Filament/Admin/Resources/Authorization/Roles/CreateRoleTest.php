<?php

use App\Domain\Authorization\Models\Role;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\Authorization\Roles\Pages\CreateRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['is_super_admin' => true]);
    $this->organization = Organization::factory()->create();
    $this->actingAs($this->user);

    // Create a permission for testing
    Permission::firstOrCreate(['name' => 'view', 'guard_name' => 'web']);
});

it('can render the create role page', function (): void {
    livewire(CreateRole::class)
        ->assertSuccessful();
});

it('can create a new role', function (): void {
    $permission = Permission::where('name', 'view')->first();

    livewire(CreateRole::class)
        ->fillForm([
            'name' => 'New Role',
            'organization_id' => $this->organization->id,
            'permissions' => [$permission->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('roles', [
        'name' => 'New Role',
        'organization_id' => $this->organization->id,
    ]);
});

it('validates required name field', function (): void {
    livewire(CreateRole::class)
        ->fillForm([
            'name' => '',
            'organization_id' => $this->organization->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('validates required organization field', function (): void {
    livewire(CreateRole::class)
        ->fillForm([
            'name' => 'Test Role',
            'organization_id' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['organization_id' => 'required']);
});

it('validates unique name per organization', function (): void {
    $existingRole = Role::factory()->create([
        'name' => 'Admin',
        'organization_id' => $this->organization->id,
    ]);

    livewire(CreateRole::class)
        ->fillForm([
            'name' => 'Admin',
            'organization_id' => $this->organization->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['name']);
});
