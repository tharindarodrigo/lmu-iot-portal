<?php

declare(strict_types=1);

namespace App\Http\Controllers\IoTDashboard;

use App\Domain\IoTDashboard\Models\IoTDashboard;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateIoTDashboardWidgetLayoutRequest;
use Illuminate\Http\Response;

class IoTDashboardWidgetLayoutController extends Controller
{
    private const int GRID_STACK_COLUMNS = 24;

    /**
     * Handle the incoming request.
     */
    public function __invoke(
        UpdateIoTDashboardWidgetLayoutRequest $request,
        IoTDashboardWidget $widget,
    ): \Illuminate\Http\JsonResponse {
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $widget->loadMissing(['dashboard', 'device:id,organization_id']);

        $dashboard = $widget->dashboard;

        if (! $dashboard instanceof IoTDashboard) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $organizationId = (int) $dashboard->organization_id;
        $belongsToOrganization = $user->organizations()->whereKey($organizationId)->exists();

        if (! $user->isSuperAdmin() && ! $belongsToOrganization) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if (
            is_numeric($widget->device_id)
            && (int) $widget->device?->organization_id !== $organizationId
        ) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $x = $request->integer('x');
        $y = $request->integer('y');
        $w = $request->integer('w');
        $h = $request->integer('h');

        $optionsValue = $widget->getAttribute('options');
        $options = is_array($optionsValue) ? $optionsValue : [];
        $options['layout'] = [
            'x' => $x,
            'y' => $y,
            'w' => $w,
            'h' => $h,
        ];
        $options['layout_columns'] = self::GRID_STACK_COLUMNS;
        $options['grid_columns'] = $w;
        $options['card_height_px'] = $h * 96;

        $widget->forceFill([
            'options' => $options,
        ])->save();

        return response()->json([
            'widget_id' => (int) $widget->id,
            'layout' => $options['layout'],
        ]);
    }
}
