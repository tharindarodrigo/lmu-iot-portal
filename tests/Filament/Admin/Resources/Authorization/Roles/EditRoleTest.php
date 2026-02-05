<?php

use App\Domain\Authorization\Models\Role;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\Authorization\Roles\Pages\EditRole;
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

it('can render the edit role page', function (): void {
    $role = Role::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    livewire(EditRole::class, ['record' => $role->getRouteKey()])
        ->assertSuccessful();
});

it('can update a role', function (): void {
    $permission = Permission::where('name', 'view')->first();
    $role = Role::factory()->create([
        'name' => 'Original Role',
        'organization_id' => $this->organization->id,
    ]);
    $role->permissions()->attach($permission);

    livewire(EditRole::class, ['record' => $role->getRouteKey()])
        ->fillForm([
            'name' => 'Updated Role',
            'permissions' => [$permission->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('roles', [
        'id' => $role->id,
        'name' => 'Updated Role',
    ]);
});

it('populates form with existing data', function (): void {
    $role = Role::factory()->create([
        'name' => 'Test Role',
        'organization_id' => $this->organization->id,
    ]);

    livewire(EditRole::class, ['record' => $role->getRouteKey()])
        ->assertFormSet([
            'name' => 'Test Role',
        ]);
});

it('validates required name field', function (): void {
    $role = Role::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    livewire(EditRole::class, ['record' => $role->getRouteKey()])
        ->fillForm([
            'name' => '',
        ])
        ->call('save')
        ->assertHasFormErrors(['name' => 'required']);
});
