<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Domain\Shared\Models\User;

class IoTDashboardOrganizationChannel
{
    /**
     * Authenticate the user's access to the channel.
     */
    public function join(User $user, int $organizationId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->organizations()->whereKey($organizationId)->exists();
    }
}
