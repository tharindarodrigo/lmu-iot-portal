<?php

declare(strict_types=1);

namespace App\Domain\IoT\Models;

use App\Domain\IoT\Enums\ValidationStatus;
use Database\Factories\Domain\IoT\Models\DeviceTelemetryLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceTelemetryLog extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\IoT\Models\DeviceTelemetryLogFactory> */
    use HasFactory;

    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['id'];

    protected static function newFactory(): DeviceTelemetryLogFactory
    {
        return DeviceTelemetryLogFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'transformed_values' => 'array',
            'validation_status' => ValidationStatus::class,
            'recorded_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<DeviceSchemaVersion, $this>
     */
    public function schemaVersion(): BelongsTo
    {
        return $this->belongsTo(DeviceSchemaVersion::class, 'device_schema_version_id');
    }
}
