<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Actions;

use App\Domain\Reporting\Models\ReportRun;
use Illuminate\Support\Facades\Storage;

class DeleteReportRunAction
{
    public function __invoke(ReportRun $reportRun): void
    {
        if (
            is_string($reportRun->storage_disk)
            && trim($reportRun->storage_disk) !== ''
            && is_string($reportRun->storage_path)
            && trim($reportRun->storage_path) !== ''
            && Storage::disk($reportRun->storage_disk)->exists($reportRun->storage_path)
        ) {
            Storage::disk($reportRun->storage_disk)->delete($reportRun->storage_path);
        }

        $reportRun->delete();
    }
}
