<?php

namespace Tests;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Het Filament-panel gebruikt ->viteTheme(); zonder gebouwde assets
        // gooit elke pagina-render een "Vite manifest not found" (bv. in CI,
        // dat de frontend niet bouwt in de test-job). Tests toetsen gedrag,
        // geen styling — dus Vite uitschakelen.
        $this->withoutVite();
    }
}
