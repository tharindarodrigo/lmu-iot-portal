<?php

declare(strict_types=1);

namespace App\Http\Controllers\IoTDashboard;

use App\Domain\IoTDashboard\Enums\BarInterval;
use App\Domain\IoTDashboard\Models\IoTDashboard;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Domain\IoTDashboard\Services\BarChartWidgetDataResolver;
use App\Domain\IoTDashboard\Services\GaugeWidgetDataResolver;
use App\Domain\IoTDashboard\Services\LineChartWidgetDataResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IoTDashboardWidgetSeriesController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        IoTDashboardWidget $widget,
        LineChartWidgetDataResolver $resolver,
        BarChartWidgetDataResolver $barChartWidgetDataResolver,
        GaugeWidgetDataResolver $gaugeWidgetDataResolver,
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

        $isBarChartWidget = $widget->type === 'bar_chart';
        $isGaugeWidget = $widget->type === 'gauge_chart';
        $maxLookbackMinutes = $isBarChartWidget ? 129600 : 1440;
        $minPoints = $isBarChartWidget ? 2 : ($isGaugeWidget ? 1 : 20);
        $lookbackMinutes = (int) min(max((int) $request->integer('lookback_minutes', $widget->lookback_minutes), 5), $maxLookbackMinutes);
        $maxPoints = (int) min(max((int) $request->integer('max_points', $widget->max_points), $minPoints), 1000);
        $barInterval = $this->resolveBarInterval($request, $widget);

        if ($isBarChartWidget) {
            return response()->json($barChartWidgetDataResolver->resolve($widget, $lookbackMinutes, $maxPoints, $barInterval));
        }

        if ($isGaugeWidget) {
            return response()->json($gaugeWidgetDataResolver->resolve($widget, $lookbackMinutes));
        }

        return response()->json($resolver->resolve($widget, $lookbackMinutes, $maxPoints));
    }

    private function resolveBarInterval(Request $request, IoTDashboardWidget $widget): BarInterval
    {
        $requestedInterval = $request->string('bar_interval')->toString();
        $fromRequest = BarInterval::tryFrom(strtolower(trim($requestedInterval)));

        if ($fromRequest instanceof BarInterval) {
            return $fromRequest;
        }

        $configuredInterval = data_get($widget->options, 'bar_interval');

        if (is_string($configuredInterval)) {
            $fromWidget = BarInterval::tryFrom(strtolower(trim($configuredInterval)));

            if ($fromWidget instanceof BarInterval) {
                return $fromWidget;
            }
        }

        return BarInterval::Hourly;
    }
}
