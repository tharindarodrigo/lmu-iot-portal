<?php

declare(strict_types=1);

namespace App\Domain\DeviceControl\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ControlWidgetTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\DeviceControl\Models\ControlWidgetTemplateFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'supports_realtime' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
