<?php

namespace App\Policies;

use App\Domain\Authorization\Models\Role;
use App\Domain\Authorization\Permissions\RolePermission;
use App\Domain\Shared\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(RolePermission::VIEW_ANY->value);
    }

    public function view(User $user, Role $model): bool
    {
        return $user->hasPermissionTo(RolePermission::VIEW->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(RolePermission::CREATE->value);
    }

    public function update(User $user, Role $model): bool
    {
        return $user->hasPermissionTo(RolePermission::UPDATE->value);
    }

    public function delete(User $user, Role $model): bool
    {
        return $user->hasPermissionTo(RolePermission::DELETE->value);
    }

    public function restore(User $user, Role $model): bool
    {
        return $user->hasPermissionTo(RolePermission::RESTORE->value);
    }

    public function forceDelete(User $user, Role $model): bool
    {
        return $user->hasPermissionTo(RolePermission::FORCE_DELETE->value);
    }
}
