<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\Models;

use Database\Factories\Domain\DeviceManagement\Models\VirtualDeviceLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VirtualDeviceLink extends Model
{
    /** @use HasFactory<VirtualDeviceLinkFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected static function newFactory(): VirtualDeviceLinkFactory
    {
        return VirtualDeviceLinkFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'int',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function virtualDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'virtual_device_id');
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function sourceDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'source_device_id');
    }
}
