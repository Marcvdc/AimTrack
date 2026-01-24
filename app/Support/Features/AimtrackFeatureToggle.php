<?php

namespace App\Support\Features;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Feature;

class AimtrackFeatureToggle
{
    protected ?bool $featuresTableExists = null;

    protected bool $missingTableLogged = false;

    public function aiEnabled(): bool
    {
        return $this->isFeatureActive('aimtrack-ai');
    }

    public function aiDisabled(): bool
    {
        return ! $this->aiEnabled();
    }

    protected function isFeatureActive(string $feature): bool
    {
        if (! $this->featuresTableExists()) {
            return $this->fallbackToDefault($feature);
        }

        return Feature::active($feature);
    }

    protected function featuresTableExists(): bool
    {
        if ($this->featuresTableExists === null) {
            $this->featuresTableExists = Schema::hasTable('features');
        }

        return $this->featuresTableExists;
    }

    protected function fallbackToDefault(string $feature): bool
    {
        $default = (bool) config("features.defaults.$feature", app()->environment('local'));

        if (! $this->missingTableLogged) {
            $this->missingTableLogged = true;

            Log::warning('Pennant features table ontbreekt; val terug op env default.', [
                'feature' => $feature,
                'default' => $default,
            ]);
        }

        return $default;
    }
}
