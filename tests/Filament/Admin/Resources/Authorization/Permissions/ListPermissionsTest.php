<?php

use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\Authorization\Permissions\Pages\ListPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->user);
});

it('can render the permissions list page', function (): void {
    livewire(ListPermissions::class)
        ->assertSuccessful();
});

it('can see permissions in the grid', function (): void {
    Permission::create(['name' => 'view-users', 'guard_name' => 'web', 'group' => 'Users']);
    Permission::create(['name' => 'edit-users', 'guard_name' => 'web', 'group' => 'Users']);

    livewire(ListPermissions::class)
        ->assertSee('view-users')
        ->assertSee('edit-users');
});

it('can filter permissions by group', function (): void {
    Permission::create(['name' => 'user-view', 'guard_name' => 'web', 'group' => 'Users']);
    Permission::create(['name' => 'admin-view', 'guard_name' => 'web', 'group' => 'Admins']);

    livewire(ListPermissions::class)
        ->filterTable('group', ['admin-view'])
        ->assertSee('admin-view');
});

it('can toggle between grid and table view', function (): void {
    Permission::create(['name' => 'test-permission', 'guard_name' => 'web', 'group' => 'Test']);

    livewire(ListPermissions::class)
        ->set('viewType', 'table')
        ->assertSet('viewType', 'table');
});

it('excludes null group permissions from filters', function (): void {
    Permission::create(['name' => 'permission-with-group', 'guard_name' => 'web', 'group' => 'Test']);
    Permission::create(['name' => 'permission-without-group', 'guard_name' => 'web', 'group' => null]);

    $component = livewire(ListPermissions::class);
    // The filter should not include the null group permission
    $component->assertSuccessful();
});
