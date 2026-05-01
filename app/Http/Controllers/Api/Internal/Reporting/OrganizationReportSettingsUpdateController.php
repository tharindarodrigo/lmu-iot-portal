<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal\Reporting;

use App\Domain\Reporting\Actions\UpdateOrganizationReportSettingsAction;
use App\Domain\Reporting\Models\OrganizationReportSetting;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrganizationReportSettingsRequest;
use Illuminate\Http\JsonResponse;

class OrganizationReportSettingsUpdateController extends Controller
{
    public function __invoke(
        UpdateOrganizationReportSettingsRequest $request,
        UpdateOrganizationReportSettingsAction $updateOrganizationReportSettingsAction,
    ): JsonResponse {
        /** @var OrganizationReportSetting $settings */
        $settings = $updateOrganizationReportSettingsAction($request->validated());

        return response()->json([
            'data' => [
                'id' => (int) $settings->id,
                'organization_id' => (int) $settings->organization_id,
                'timezone' => (string) $settings->timezone,
                'max_range_days' => (int) $settings->max_range_days,
                'shift_schedules' => $settings->shift_schedules ?? [],
            ],
        ]);
    }
}
