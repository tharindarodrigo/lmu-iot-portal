<?php

namespace App\Policies;

use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use App\Domain\Shared\Permissions\OrganizationPermission;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(OrganizationPermission::VIEW_ANY);
    }

    public function view(User $user, Organization $model): bool
    {
        return $user->hasPermissionTo(OrganizationPermission::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(OrganizationPermission::CREATE);
    }

    public function update(User $user, Organization $model): bool
    {
        return $user->hasPermissionTo(OrganizationPermission::UPDATE);
    }

    public function delete(User $user, Organization $model): bool
    {
        return $user->hasPermissionTo(OrganizationPermission::DELETE);
    }

    public function restore(User $user, Organization $model): bool
    {
        return $user->hasPermissionTo(OrganizationPermission::RESTORE);
    }

    public function forceDelete(User $user, Organization $model): bool
    {
        return $user->hasPermissionTo(OrganizationPermission::FORCE_DELETE);
    }
}
