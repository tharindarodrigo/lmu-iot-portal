<?php

namespace Database\Seeders;

use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Organization::factory()
            ->times(10)
            ->create()
            ->each(function (Organization $organization) {
                $previousPermissionsTeamId = getPermissionsTeamId();
                setPermissionsTeamId($organization->id);

                try {
                    /** @var User $adminUser */
                    $adminUser = User::factory()->create();
                    $organization->users()->attach($adminUser);

                    $superAdmin = User::find(1);
                    $organization->users()->attach($superAdmin);

                    $role = $organization->roles()->create([
                        'name' => 'admin',
                        'guard_name' => 'web',
                    ]);

                    $permissions = Permission::all()->pluck('name')->toArray();
                    $role->syncPermissions($permissions);

                    $adminUser->assignRole($role);

                    $users = User::factory()->count(5)->create();
                    $organization->users()->attach($users);
                } finally {
                    setPermissionsTeamId($previousPermissionsTeamId);
                }
            });
    }
}
