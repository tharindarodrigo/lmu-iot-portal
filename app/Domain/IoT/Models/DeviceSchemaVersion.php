<?php

declare(strict_types=1);

namespace App\Domain\IoT\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceSchemaVersion extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\IoT\Models\DeviceSchemaVersionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<DeviceSchema, $this>
     */
    public function schema(): BelongsTo
    {
        return $this->belongsTo(DeviceSchema::class, 'device_schema_id');
    }

    /**
     * @return HasMany<ParameterDefinition, $this>
     */
    public function parameters(): HasMany
    {
        return $this->hasMany(ParameterDefinition::class, 'device_schema_version_id');
    }

    /**
     * @return HasMany<DerivedParameterDefinition, $this>
     */
    public function derivedParameters(): HasMany
    {
        return $this->hasMany(DerivedParameterDefinition::class, 'device_schema_version_id');
    }

    /**
     * @return HasMany<DeviceTelemetryLog, $this>
     */
    public function telemetryLogs(): HasMany
    {
        return $this->hasMany(DeviceTelemetryLog::class, 'device_schema_version_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
