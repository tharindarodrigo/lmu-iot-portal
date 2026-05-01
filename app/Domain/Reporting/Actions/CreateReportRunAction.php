<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Actions;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\Reporting\Enums\ReportGrouping;
use App\Domain\Reporting\Enums\ReportRunStatus;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Jobs\GenerateReportRunJob;
use App\Domain\Reporting\Models\ReportRun;
use App\Domain\Reporting\Services\ReportRunPayloadValidator;
use App\Domain\Shared\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CreateReportRunAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(User $user, array $payload): ReportRun
    {
        /** @var array<string, mixed> $validatedPayload */
        $validatedPayload = app(ReportRunPayloadValidator::class)->validate([
            ...$payload,
            'requested_by_user_id' => (int) $user->id,
        ]);

        $organizationId = $this->integerPayloadValue($validatedPayload, 'organization_id');

        if ($organizationId <= 0) {
            throw ValidationException::withMessages([
                'organization_id' => 'The selected organization is invalid.',
            ]);
        }

        if (! $user->isSuperAdmin() && ! $user->organizations()->whereKey($organizationId)->exists()) {
            throw ValidationException::withMessages([
                'organization_id' => 'The requesting user does not have access to this organization.',
            ]);
        }

        $deviceId = $this->integerPayloadValue($validatedPayload, 'device_id');
        $device = Device::query()
            ->whereKey($deviceId)
            ->where('organization_id', $organizationId)
            ->first();

        if (! $device instanceof Device) {
            throw ValidationException::withMessages([
                'device_id' => 'The selected device is invalid for the chosen organization.',
            ]);
        }

        $type = $this->resolveReportType($validatedPayload['type'] ?? null);
        $reportRun = ReportRun::query()->create([
            'organization_id' => $organizationId,
            'device_id' => (int) $device->id,
            'requested_by_user_id' => (int) $user->id,
            'type' => $type,
            'status' => ReportRunStatus::Queued,
            'format' => $this->resolveFormat($validatedPayload['format'] ?? null),
            'grouping' => $this->resolveReportGrouping($validatedPayload['grouping'] ?? null, $type),
            'parameter_keys' => $this->normalizeParameterKeys($validatedPayload['parameter_keys'] ?? null),
            'from_at' => $this->resolveDate($validatedPayload['from_at'] ?? null, 'from_at'),
            'until_at' => $this->resolveDate($validatedPayload['until_at'] ?? null, 'until_at'),
            'timezone' => $this->resolveTimezone($validatedPayload['timezone'] ?? null),
            'payload' => is_array($validatedPayload['payload'] ?? null) ? $validatedPayload['payload'] : null,
        ]);

        GenerateReportRunJob::dispatch((int) $reportRun->id);

        $reportRun->loadMissing(['organization:id,name', 'device:id,name,external_id', 'requestedBy:id,name']);

        return $reportRun;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function integerPayloadValue(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function resolveReportType(mixed $value): ReportType
    {
        if ($value instanceof ReportType) {
            return $value;
        }

        if (is_string($value)) {
            $resolvedType = ReportType::tryFrom($value);

            if ($resolvedType instanceof ReportType) {
                return $resolvedType;
            }
        }

        throw ValidationException::withMessages([
            'type' => 'The selected report type is invalid.',
        ]);
    }

    private function resolveReportGrouping(mixed $value, ReportType $type): ?ReportGrouping
    {
        if (! in_array($type, [ReportType::CounterConsumption, ReportType::StateUtilization], true)) {
            return null;
        }

        if ($value instanceof ReportGrouping) {
            return $value;
        }

        if (is_string($value)) {
            $resolvedGrouping = ReportGrouping::tryFrom($value);

            if ($resolvedGrouping instanceof ReportGrouping) {
                return $resolvedGrouping;
            }
        }

        throw ValidationException::withMessages([
            'grouping' => 'The selected aggregation window is invalid.',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeParameterKeys(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $parameterKey): ?string {
            if (! is_scalar($parameterKey) && ! $parameterKey instanceof \Stringable) {
                return null;
            }

            $resolvedParameterKey = trim((string) $parameterKey);

            return $resolvedParameterKey !== '' ? $resolvedParameterKey : null;
        }, $value)));
    }

    private function resolveDate(mixed $value, string $key): Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            throw ValidationException::withMessages([
                $key => "The {$key} value is not a valid date/time.",
            ]);
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                $key => "The {$key} value is not a valid date/time.",
            ]);
        }
    }

    private function resolveTimezone(mixed $value): string
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        $timezone = config('app.timezone', 'UTC');

        return is_string($timezone) ? $timezone : 'UTC';
    }

    private function resolveFormat(mixed $value): string
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return 'csv';
    }
}
