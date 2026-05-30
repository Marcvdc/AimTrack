<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\DemoDataSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Browser-testing seeder voor de Copilot/AI flows. Zorgt dat
 * admin@aimtrack.test bestaat, wist eerdere demo-data, en roept
 * vervolgens de centrale DemoDataSeeder aan. Daardoor hoeft de
 * fixture-data maar op één plek onderhouden te worden — de Filament
 * "Demo-data inladen"-actie uit Fase 2 gebruikt dezelfde service.
 */
class CopilotDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'admin@aimtrack.test'],
            [
                'name' => 'Demo Schutter',
                'password' => Hash::make('admin12345'),
                'email_verified_at' => now(),
            ],
        );

        /** @var DemoDataSeeder $seeder */
        $seeder = app(DemoDataSeeder::class);
        $seeder->purgeFor($user);
        $seeder->seedFor($user);

        $this->command?->info("Demo data geladen voor {$user->email} ({$user->sessions()->count()} sessies, {$user->weapons()->count()} wapens).");
    }
}
