<?php

declare(strict_types=1);

namespace App\Domain\Automation\Models;

use App\Domain\Alerts\Models\NotificationProfile;
use Database\Factories\Domain\Automation\Models\AutomationNotificationProfileFactory;

class AutomationNotificationProfile extends NotificationProfile
{
    protected static function newFactory(): AutomationNotificationProfileFactory
    {
        return AutomationNotificationProfileFactory::new();
    }
}
