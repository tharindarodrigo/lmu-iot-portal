<?php

declare(strict_types=1);

namespace App\Domain\Automation\Models;

use App\Domain\Alerts\Models\ThresholdPolicy;
use Database\Factories\Domain\Automation\Models\AutomationThresholdPolicyFactory;

class AutomationThresholdPolicy extends ThresholdPolicy
{
    protected static function newFactory(): AutomationThresholdPolicyFactory
    {
        return AutomationThresholdPolicyFactory::new();
    }
}
