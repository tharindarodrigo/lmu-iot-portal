<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Actions;

use App\Domain\Reporting\Enums\ReportRunStatus;
use App\Domain\Reporting\Models\ReportRun;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DownloadReportRunAction
{
    public function __invoke(ReportRun $reportRun): StreamedResponse
    {
        if ($reportRun->status !== ReportRunStatus::Completed) {
            throw new HttpException(Response::HTTP_CONFLICT, 'The selected report is not ready for download.');
        }

        if (! $reportRun->isDownloadable()) {
            throw new NotFoundHttpException('The selected report file could not be found.');
        }

        $disk = trim((string) $reportRun->storage_disk);
        $path = trim((string) $reportRun->storage_path);

        if ($disk === '' || $path === '') {
            throw new NotFoundHttpException('The selected report file could not be found.');
        }

        $filesystem = Storage::disk($disk);

        if (! $filesystem->exists($path)) {
            throw new NotFoundHttpException('The selected report file could not be found.');
        }

        $downloadName = is_string($reportRun->file_name) && trim($reportRun->file_name) !== ''
            ? $reportRun->file_name
            : "report-{$reportRun->id}.csv";

        return response()->streamDownload(
            function () use ($filesystem, $path): void {
                $stream = $filesystem->readStream($path);

                if (! is_resource($stream)) {
                    throw new NotFoundHttpException('The selected report file could not be found.');
                }

                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            },
            $downloadName,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
