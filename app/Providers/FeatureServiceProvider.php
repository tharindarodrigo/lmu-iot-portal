<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class FeatureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Feature::define('ingestion.pipeline.enabled', fn (): bool => (bool) config('ingestion.enabled', true));

        Feature::define('ingestion.pipeline.driver', function (): string {
            $driver = config('ingestion.driver', 'laravel');

            return is_string($driver) && $driver !== '' ? $driver : 'laravel';
        });

        Feature::define('ingestion.pipeline.broadcast_realtime', fn (): bool => (bool) config('ingestion.broadcast_realtime', true));
        Feature::define('ingestion.pipeline.publish_analytics', fn (): bool => (bool) config('ingestion.publish_analytics', true));
        Feature::define('automation.pipeline.telemetry_fanout', fn (): bool => (bool) config('automation.telemetry_fanout_enabled', true));
    }
}
