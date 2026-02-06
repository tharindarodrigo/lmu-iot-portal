<?php

declare(strict_types=1);

namespace App\Domain\IoT\Support;

use App\Domain\IoT\Enums\ValidationStatus;
use App\Domain\IoT\Models\DerivedParameterDefinition;
use App\Domain\IoT\Models\Device;
use App\Domain\IoT\Models\DeviceSchemaVersion;
use App\Domain\IoT\Models\DeviceTelemetryLog;
use App\Domain\IoT\Models\ParameterDefinition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class TelemetryLogRecorder
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(Device $device, array $payload, ?Carbon $recordedAt = null, ?Carbon $receivedAt = null): DeviceTelemetryLog
    {
        $device->loadMissing('schemaVersion');

        $schemaVersion = $device->schemaVersion;

        if (! $schemaVersion instanceof DeviceSchemaVersion) {
            throw new RuntimeException('Device schema version is required to record telemetry logs.');
        }

        $parameters = $schemaVersion->parameters()
            ->where('is_active', true)
            ->orderBy('sequence')
            ->get();

        $derivedParameters = $schemaVersion->derivedParameters()->get();

        [$transformedValues, $validationStatus] = $this->evaluatePayload($payload, $parameters, $derivedParameters);

        $resolvedReceivedAt = $receivedAt ?? Carbon::now();
        $resolvedRecordedAt = $recordedAt ?? $resolvedReceivedAt;

        return DeviceTelemetryLog::create([
            'device_id' => $device->id,
            'device_schema_version_id' => $schemaVersion->id,
            'raw_payload' => $payload,
            'transformed_values' => $transformedValues,
            'validation_status' => $validationStatus,
            'recorded_at' => $resolvedRecordedAt,
            'received_at' => $resolvedReceivedAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  Collection<int, ParameterDefinition>  $parameters
     * @param  Collection<int, DerivedParameterDefinition>  $derivedParameters
     * @return array{0: array<string, mixed>, 1: ValidationStatus}
     */
    private function evaluatePayload(array $payload, Collection $parameters, Collection $derivedParameters): array
    {
        $transformedValues = [];
        $hasInvalid = false;
        $hasCriticalInvalid = false;

        foreach ($parameters as $parameter) {
            $result = $parameter->evaluatePayload($payload);

            $transformedValues[$parameter->key] = $result['mutated'];

            if ($result['validation']['is_valid'] === false) {
                $hasInvalid = true;

                if ($result['validation']['is_critical'] === true) {
                    $hasCriticalInvalid = true;
                }
            }
        }

        $derivedValues = $this->evaluateDerivedParameters($derivedParameters, $transformedValues);

        $transformedValues = array_merge($transformedValues, $derivedValues);

        $status = match (true) {
            $hasCriticalInvalid => ValidationStatus::Invalid,
            $hasInvalid => ValidationStatus::Warning,
            default => ValidationStatus::Valid,
        };

        return [$transformedValues, $status];
    }

    /**
     * @param  Collection<int, DerivedParameterDefinition>  $derivedParameters
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function evaluateDerivedParameters(Collection $derivedParameters, array $inputs): array
    {
        $pending = $derivedParameters->keyBy('key')->all();
        $resolved = $inputs;
        $derivedValues = [];
        $maxIterations = count($pending);
        $iterations = 0;

        while ($pending !== [] && $iterations < $maxIterations) {
            $progress = false;

            foreach ($pending as $key => $definition) {
                $dependencies = $definition->resolvedDependencies();

                if (array_diff($dependencies, array_keys($resolved)) !== []) {
                    continue;
                }

                $value = $definition->evaluate($resolved);
                $derivedValues[$key] = $value;
                $resolved[$key] = $value;
                unset($pending[$key]);
                $progress = true;
            }

            if (! $progress) {
                break;
            }

            $iterations++;
        }

        return $derivedValues;
    }
}
