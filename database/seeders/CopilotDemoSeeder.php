<?php

namespace Database\Seeders;

use App\Enums\Deviation;
use App\Enums\WeaponType;
use App\Models\AiReflection;
use App\Models\Session;
use App\Models\SessionWeapon;
use App\Models\User;
use App\Models\Weapon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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

        $cz = Weapon::query()->updateOrCreate(
            ['user_id' => $user->id, 'serial_number' => 'CZ-DEMO-01'],
            [
                'name' => 'CZ Shadow 2',
                'weapon_type' => WeaponType::PISTOL,
                'caliber' => '9mm',
                'storage_location' => 'Kluis A',
                'is_active' => true,
                'owned_since' => now()->subYears(2),
                'notes' => 'Wedstrijdpistool, primaire keuze 25m precisie.',
            ],
        );

        $glock = Weapon::query()->updateOrCreate(
            ['user_id' => $user->id, 'serial_number' => 'GLK-DEMO-02'],
            [
                'name' => 'Glock 17',
                'weapon_type' => WeaponType::PISTOL,
                'caliber' => '9mm',
                'storage_location' => 'Kluis A',
                'is_active' => true,
                'owned_since' => now()->subYears(1),
                'notes' => 'Service-pistool, voor snelvuur op 15m.',
            ],
        );

        $beretta = Weapon::query()->updateOrCreate(
            ['user_id' => $user->id, 'serial_number' => 'BER-DEMO-03'],
            [
                'name' => 'Beretta 87 Target',
                'weapon_type' => WeaponType::PISTOL,
                'caliber' => '.22LR',
                'storage_location' => 'Kluis A',
                'is_active' => true,
                'owned_since' => now()->subMonths(8),
                'notes' => 'Trainingspistool, focus op trekkertechniek.',
            ],
        );

        $sessions = [
            [
                'days_ago' => 2,
                'range_name' => 'KSV De Roos',
                'location' => 'Eindhoven',
                'notes_raw' => 'Goede dag, rustige ademhaling, focus op trekker.',
                'manual_reflection' => 'Iets te veel druk op de trekker bij de laatste serie.',
                'shots' => [
                    [$cz, 25, 30, Deviation::HIGH, 'Strak gegroepeerd, lichte high tendens'],
                    [$beretta, 25, 50, Deviation::NONE, 'Schone rooster, goede grouping'],
                ],
                'reflection' => [
                    'summary' => 'Sterke 25m sessie met de CZ Shadow 2; lichte tendens naar boven door anticipatie.',
                    'positives' => ['Stabiele houding', 'Consistente ademhaling'],
                    'improvements' => ['Trekker-druk gelijkmatiger', 'Volg-door verlengen'],
                    'next_focus' => 'Dry-fire drills met snap caps voor trekkerwerk.',
                ],
            ],
            [
                'days_ago' => 6,
                'range_name' => 'KSV De Roos',
                'location' => 'Eindhoven',
                'notes_raw' => 'Snelvuur-training met de Glock op 15m.',
                'manual_reflection' => 'Tweede serie liep beter dan de eerste.',
                'shots' => [
                    [$glock, 15, 60, Deviation::LEFT, 'Patroon trekt links — grip checken'],
                ],
                'reflection' => null,
            ],
            [
                'days_ago' => 10,
                'range_name' => 'KSV De Roos',
                'location' => 'Eindhoven',
                'notes_raw' => 'Lange precisie-sessie op 25m.',
                'manual_reflection' => null,
                'shots' => [
                    [$cz, 25, 40, Deviation::NONE, 'Mooi gecentreerd'],
                    [$beretta, 25, 60, Deviation::LOW, 'Flyers in 4e serie, vermoeidheid'],
                ],
                'reflection' => null,
            ],
            [
                'days_ago' => 17,
                'range_name' => 'SV Diana',
                'location' => 'Veldhoven',
                'notes_raw' => 'Eerste sessie op nieuwe baan, andere belichting dan thuisbaan.',
                'manual_reflection' => 'Bezoek nieuwe baan was leerzaam, andere achtergrond verstoorde focus.',
                'shots' => [
                    [$cz, 25, 30, Deviation::RIGHT, 'Lichte right-pull, bredere grouping'],
                ],
                'reflection' => null,
            ],
            [
                'days_ago' => 24,
                'range_name' => 'KSV De Roos',
                'location' => 'Eindhoven',
                'notes_raw' => 'Combinatie precisie + snelvuur.',
                'manual_reflection' => 'Pols moe na 60 schoten Glock — pauze inlassen.',
                'shots' => [
                    [$glock, 15, 50, Deviation::HIGH, 'Anticipatie na recoil zichtbaar'],
                    [$cz, 25, 30, Deviation::NONE, 'Mooie groep, focus terug'],
                ],
                'reflection' => null,
            ],
        ];

        foreach ($sessions as $config) {
            $session = Session::query()->create([
                'user_id' => $user->id,
                'date' => now()->subDays($config['days_ago'])->toDateString(),
                'range_name' => $config['range_name'],
                'location' => $config['location'],
                'notes_raw' => $config['notes_raw'],
                'manual_reflection' => $config['manual_reflection'],
            ]);

            foreach ($config['shots'] as [$weapon, $distance, $rounds, $deviation, $quality]) {
                SessionWeapon::query()->create([
                    'session_id' => $session->id,
                    'weapon_id' => $weapon->id,
                    'distance_m' => $distance,
                    'rounds_fired' => $rounds,
                    'ammo_type' => $weapon->caliber === '.22LR' ? '.22LR club' : '9mm FMJ 124gr',
                    'deviation' => $deviation,
                    'group_quality_text' => $quality,
                    'flyers_count' => 0,
                ]);
            }

            if ($config['reflection']) {
                AiReflection::query()->create([
                    'session_id' => $session->id,
                    ...$config['reflection'],
                ]);
            }
        }

        $this->command?->info("Demo data geladen voor {$user->email} ({$user->sessions()->count()} sessies, {$user->weapons()->count()} wapens).");
    }
}
