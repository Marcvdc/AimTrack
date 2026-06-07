<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Uitkomst van DemoDataSeeder::seedFor() — voor de UI-laag een
 * duidelijk verschil tussen "net geladen" en "al eerder geladen".
 */
enum SeedResult: string
{
    case Seeded = 'seeded';
    case AlreadyLoaded = 'already_loaded';
}
