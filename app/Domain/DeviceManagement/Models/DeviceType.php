<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\Models;

use App\Domain\DeviceManagement\Casts\ProtocolConfigCast;
use App\Domain\DeviceManagement\Enums\ProtocolType;
use App\Domain\DeviceManagement\Services\VirtualStandardProfileRegistry;
use App\Domain\DeviceManagement\ValueObjects\Protocol\ProtocolConfigInterface;
use App\Domain\DeviceManagement\ValueObjects\VirtualStandards\VirtualStandardProfile;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\Shared\Models\Organization;
use Database\Factories\Domain\DeviceManagement\Models\DeviceTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property ProtocolConfigInterface|null $protocol_config
 * @property ProtocolType $default_protocol
 * @property array<string, mixed>|null $virtual_standard_profile
 */
class DeviceType extends Model
{
    /** @use HasFactory<DeviceTypeFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected static function newFactory(): DeviceTypeFactory
    {
        return DeviceTypeFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_protocol' => ProtocolType::class,
            'protocol_config' => ProtocolConfigCast::class,
            'virtual_standard_profile' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<DeviceSchema, $this>
     */
    public function schemas(): HasMany
    {
        return $this->hasMany(DeviceSchema::class, 'device_type_id');
    }

    /**
     * @return HasManyThrough<DeviceSchemaVersion, DeviceSchema, $this>
     */
    public function schemaVersions(): HasManyThrough
    {
        return $this->hasManyThrough(
            DeviceSchemaVersion::class,
            DeviceSchema::class,
            'device_type_id',
            'device_schema_id',
        );
    }

    /**
     * Check if this is a global catalog entry.
     */
    public function isGlobal(): bool
    {
        return $this->organization_id === null;
    }

    /**
     * Get the protocol configuration as a typed object.
     */
    public function getProtocolConfig(): ?ProtocolConfigInterface
    {
        return $this->protocol_config;
    }

    public function virtualStandardProfile(): ?VirtualStandardProfile
    {
        return app(VirtualStandardProfileRegistry::class)->forDeviceType($this);
    }

    public function isVirtualStandard(): bool
    {
        return $this->virtualStandardProfile() !== null;
    }

    /**
     * Scope to only global catalog entries.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('organization_id');
    }

    /**
     * Scope to organization-specific entries.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForOrganization($query, int|Organization $organization)
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return $query->where('organization_id', $organizationId);
    }
}
