<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal\Reporting;

use App\Domain\Reporting\Actions\DeleteReportRunAction;
use App\Domain\Reporting\Models\ReportRun;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportRunDestroyController extends Controller
{
    public function __invoke(
        Request $request,
        ReportRun $reportRun,
        DeleteReportRunAction $deleteReportRunAction,
    ): Response {
        $organizationId = $request->integer('organization_id');

        abort_unless($organizationId > 0 && (int) $reportRun->organization_id === $organizationId, Response::HTTP_NOT_FOUND);

        $deleteReportRunAction($reportRun);

        return response()->noContent();
    }
}
