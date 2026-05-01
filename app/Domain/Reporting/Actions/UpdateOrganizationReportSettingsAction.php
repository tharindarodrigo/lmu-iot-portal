<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Actions;

use App\Domain\Reporting\Models\OrganizationReportSetting;
use App\Domain\Reporting\Services\OrganizationReportSettingsPayloadValidator;
use Illuminate\Support\Str;

class UpdateOrganizationReportSettingsAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(array $payload): OrganizationReportSetting
    {
        /** @var array<string, mixed> $validatedPayload */
        $validatedPayload = app(OrganizationReportSettingsPayloadValidator::class)->validate($payload);

        $organizationIdValue = $validatedPayload['organization_id'] ?? null;
        $organizationId = is_numeric($organizationIdValue) ? (int) $organizationIdValue : 0;

        return OrganizationReportSetting::query()->updateOrCreate(
            ['organization_id' => $organizationId],
            [
                'timezone' => $this->resolveTimezone($validatedPayload['timezone'] ?? null),
                'max_range_days' => $this->resolveMaxRangeDays($validatedPayload['max_range_days'] ?? null),
                'shift_schedules' => $this->normalizeShiftSchedules($validatedPayload['shift_schedules'] ?? null),
            ],
        );
    }

    private function resolveTimezone(mixed $value): string
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        $timezone = config('app.timezone', 'UTC');

        return is_string($timezone) ? $timezone : 'UTC';
    }

    private function resolveMaxRangeDays(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        $defaultMaxRangeDays = config('reporting.default_max_range_days', 31);

        return is_numeric($defaultMaxRangeDays) ? (int) $defaultMaxRangeDays : 31;
    }

    /**
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     windows: array<int, array{id: string, name: string, start: string, end: string}>
     * }>
     */
    private function normalizeShiftSchedules(mixed $shiftSchedules): array
    {
        if (! is_array($shiftSchedules)) {
            return [];
        }

        $normalizedSchedules = [];

        foreach ($shiftSchedules as $shiftSchedule) {
            if (! is_array($shiftSchedule)) {
                continue;
            }

            $name = $this->stringFromArray($shiftSchedule, 'name');
            $scheduleId = $this->stringFromArray($shiftSchedule, 'id');
            $windows = is_array($shiftSchedule['windows'] ?? null) ? $shiftSchedule['windows'] : [];

            if ($name === '' || $windows === []) {
                continue;
            }

            $normalizedWindows = [];

            foreach ($windows as $window) {
                if (! is_array($window)) {
                    continue;
                }

                $windowId = $this->stringFromArray($window, 'id');
                $windowName = $this->stringFromArray($window, 'name');
                $start = $this->stringFromArray($window, 'start');
                $end = $this->stringFromArray($window, 'end');

                if ($windowName === '' || $start === '' || $end === '') {
                    continue;
                }

                $normalizedWindows[] = [
                    'id' => $windowId !== '' ? $windowId : (string) Str::ulid(),
                    'name' => $windowName,
                    'start' => $start,
                    'end' => $end,
                ];
            }

            if ($normalizedWindows === []) {
                continue;
            }

            $normalizedSchedules[] = [
                'id' => $scheduleId !== '' ? $scheduleId : (string) Str::ulid(),
                'name' => $name,
                'windows' => $normalizedWindows,
            ];
        }

        return $normalizedSchedules;
    }

    /**
     * @param  array<mixed, mixed>  $source
     */
    private function stringFromArray(array $source, string $key): string
    {
        $value = $source[$key] ?? null;

        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            return '';
        }

        return trim((string) $value);
    }
}
