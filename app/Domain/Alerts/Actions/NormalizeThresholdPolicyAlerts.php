<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Actions;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Alerts\Models\ThresholdPolicy;
use App\Domain\Alerts\Services\AlertIncidentManager;

class NormalizeThresholdPolicyAlerts
{
    public function __construct(
        private readonly AlertIncidentManager $alertIncidentManager,
    ) {}

    public function __invoke(ThresholdPolicy $policy): ?Alert
    {
        return $this->alertIncidentManager->normalizeThresholdAlert($policy);
    }
}
