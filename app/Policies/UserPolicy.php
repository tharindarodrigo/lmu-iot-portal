<?php

namespace App\Policies;

use App\Domain\Shared\Models\User;
use App\Domain\Shared\Permissions\UserPermission;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(UserPermission::VIEW_ANY);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermissionTo(UserPermission::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(UserPermission::CREATE);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermissionTo(UserPermission::UPDATE);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasPermissionTo(UserPermission::DELETE);
    }

    public function restore(User $user, User $model): bool
    {
        return $user->hasPermissionTo(UserPermission::RESTORE);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasPermissionTo(UserPermission::FORCE_DELETE);
    }
}
