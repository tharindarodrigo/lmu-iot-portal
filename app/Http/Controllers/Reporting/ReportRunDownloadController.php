<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

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
        $user = $request->user();

        abort_unless($user !== null && $user->can('view', $reportRun), Response::HTTP_FORBIDDEN);

        return $downloadReportRunAction($reportRun);
    }
}
