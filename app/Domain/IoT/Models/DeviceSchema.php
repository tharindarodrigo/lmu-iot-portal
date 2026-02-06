<?php

declare(strict_types=1);

namespace App\Domain\IoT\Models;

use App\Domain\DeviceTypes\Models\DeviceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeviceSchema extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\IoT\Models\DeviceSchemaFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<DeviceType, $this>
     */
    public function deviceType(): BelongsTo
    {
        return $this->belongsTo(DeviceType::class);
    }

    /**
     * @return HasMany<DeviceSchemaVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DeviceSchemaVersion::class);
    }
}
