<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class FeatureServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Feature::define('aimtrack-ai', function (): bool {
            return (bool) env('FEATURE_AIMTRACK_AI', app()->environment('local'));
        });
    }
}
