<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal\Reporting;

use App\Domain\Reporting\Actions\CreateReportRunAction;
use App\Domain\Shared\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRunRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ReportRunStoreController extends Controller
{
    public function __invoke(
        StoreReportRunRequest $request,
        CreateReportRunAction $createReportRunAction,
    ): JsonResponse {
        $requestedByUserId = $request->integer('requested_by_user_id');

        $requestedBy = User::query()->find($requestedByUserId);

        if (! $requestedBy instanceof User) {
            throw ValidationException::withMessages([
                'requested_by_user_id' => 'The selected requesting user is invalid.',
            ]);
        }

        $payload = $request->validated();
        unset($payload['requested_by_user_id']);

        $reportRun = $createReportRunAction($requestedBy, $payload);

        return response()->json([
            'data' => [
                'id' => (int) $reportRun->id,
                'organization_id' => (int) $reportRun->organization_id,
                'device_id' => (int) $reportRun->device_id,
                'status' => $reportRun->status->value,
                'type' => $reportRun->type->value,
                'created_at' => $reportRun->created_at?->toIso8601String(),
            ],
        ], Response::HTTP_CREATED);
    }
}
