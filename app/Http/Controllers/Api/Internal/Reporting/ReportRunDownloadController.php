<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal\Reporting;

use App\Domain\Reporting\Actions\DownloadReportRunAction;
use App\Domain\Reporting\Models\ReportRun;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportRunDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        ReportRun $reportRun,
        DownloadReportRunAction $downloadReportRunAction,
    ): Response {
        $organizationId = $request->integer('organization_id');

        abort_unless($organizationId > 0 && (int) $reportRun->organization_id === $organizationId, Response::HTTP_NOT_FOUND);

        return $downloadReportRunAction($reportRun);
    }
}
