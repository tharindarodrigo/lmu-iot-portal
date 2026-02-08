<?php

declare(strict_types=1);

namespace App\Domain\IoT\Models;

use App\Domain\IoT\Enums\TopicDirection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property TopicDirection $direction
 */
class SchemaVersionTopic extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\IoT\Models\SchemaVersionTopicFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => TopicDirection::class,
            'qos' => 'integer',
            'retain' => 'boolean',
            'sequence' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DeviceSchemaVersion, $this>
     */
    public function schemaVersion(): BelongsTo
    {
        return $this->belongsTo(DeviceSchemaVersion::class, 'device_schema_version_id');
    }

    /**
     * @return HasMany<ParameterDefinition, $this>
     */
    public function parameters(): HasMany
    {
        return $this->hasMany(ParameterDefinition::class, 'schema_version_topic_id');
    }

    /**
     * @return HasMany<DeviceCommandLog, $this>
     */
    public function commandLogs(): HasMany
    {
        return $this->hasMany(DeviceCommandLog::class, 'schema_version_topic_id');
    }

    /**
     * Resolve the full MQTT topic for a given device.
     *
     * Full topic = {baseTopic}/{deviceIdentifier}/{suffix}
     * Uses external_id when available, falling back to uuid.
     */
    public function resolvedTopic(Device $device): string
    {
        $device->loadMissing('deviceType');

        $baseTopic = $device->deviceType?->protocol_config?->getBaseTopic() ?? '';
        $identifier = $device->external_id ?: $device->uuid;

        return trim($baseTopic, '/').'/'.$identifier.'/'.$this->suffix;
    }

    public function isPublish(): bool
    {
        return $this->direction === TopicDirection::Publish;
    }

    public function isSubscribe(): bool
    {
        return $this->direction === TopicDirection::Subscribe;
    }
}
