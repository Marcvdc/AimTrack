<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Deviation;
use App\Enums\WeaponType;
use App\Models\AiReflection;
use App\Models\Session;
use App\Models\SessionWeapon;
use App\Models\User;
use App\Models\Weapon;
use Illuminate\Support\Facades\DB;

/**
 * Centrale service voor het inladen van demo-data voor een specifieke
 * gebruiker. Wordt gebruikt door:
 *  - de Filament "Demo-data inladen"-actie in empty states (per-user
 *    klikflow, idempotent via users.demo_data_seeded_at)
 *  - CopilotDemoSeeder (Artisan db:seed --class=…) — dat ververst de
 *    marker voor convenience bij dev-resets
 *
 * Idempotency: een tweede aanroep zonder reset doet niets en retourneert
 * SeedResult::AlreadyLoaded.
 */
final class DemoDataSeeder
{
    public function seedFor(User $user): SeedResult
    {
        if ($user->demo_data_seeded_at !== null) {
            return SeedResult::AlreadyLoaded;
        }

        DB::transaction(function () use ($user): void {
            $weapons = $this->seedWeapons($user);
            $this->seedSessions($user, $weapons);
            $user->forceFill(['demo_data_seeded_at' => now()])->save();
        });

        return SeedResult::Seeded;
    }

    /**
     * Wist ALLE wapens en sessies van een gebruiker (niet alleen records
     * die door seedFor zijn aangemaakt) en reset de marker. Uitsluitend
     * bedoeld voor dev-flows zoals CopilotDemoSeeder die het wegwerp-
     * fixture-account admin@aimtrack.test herhaaldelijk reseeden tijdens
     * browsertesten. NIET wired in de Filament-UI; roep dit nooit aan op
     * een echt gebruikersaccount.
     *
     * Het verwijderen van sessions cascadeert op DB-niveau naar
     * session_weapons en ai_reflections (cascadeOnDelete op session_id),
     * dus losse child-deletes zijn niet nodig.
     */
    public function purgeFor(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->sessions()->delete();
            $user->weapons()->delete();
            $user->forceFill(['demo_data_seeded_at' => null])->save();
        });
    }

    /**
     * @return array{cz: Weapon, glock: Weapon, beretta: Weapon}
     */
    private function seedWeapons(User $user): array
    {
        $cz = Weapon::query()->create([
            'user_id' => $user->id,
            'name' => 'CZ Shadow 2',
            'weapon_type' => WeaponType::PISTOL,
            'caliber' => '9mm',
            'serial_number' => "CZ-DEMO-{$user->id}",
            'storage_location' => 'Kluis A',
            'is_active' => true,
            'owned_since' => now()->subYears(2),
            'notes' => 'Wedstrijdpistool, primaire keuze 25m precisie.',
        ]);

        $glock = Weapon::query()->create([
            'user_id' => $user->id,
            'name' => 'Glock 17',
            'weapon_type' => WeaponType::PISTOL,
            'caliber' => '9mm',
            'serial_number' => "GLK-DEMO-{$user->id}",
            'storage_location' => 'Kluis A',
            'is_active' => true,
            'owned_since' => now()->subYears(1),
            'notes' => 'Service-pistool, voor snelvuur op 15m.',
        ]);

        $beretta = Weapon::query()->create([
            'user_id' => $user->id,
            'name' => 'Beretta 87 Target',
            'weapon_type' => WeaponType::PISTOL,
            'caliber' => '.22LR',
            'serial_number' => "BER-DEMO-{$user->id}",
            'storage_location' => 'Kluis A',
            'is_active' => true,
            'owned_since' => now()->subMonths(8),
            'notes' => 'Trainingspistool, focus op trekkertechniek.',
        ]);

        return ['cz' => $cz, 'glock' => $glock, 'beretta' => $beretta];
    }

    /**
     * @param  array{cz: Weapon, glock: Weapon, beretta: Weapon}  $weapons
     */
    private function seedSessions(User $user, array $weapons): void
    {
        foreach ($this->sessionsConfig($weapons) as $config) {
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
    }

    /**
     * 5 sessies met 3 AI-reflecties — zodat de AI-coach drempel direct
     * unlocked is (3 ≥ 3 sessies) én de reflectie-UI gevuld lijkt.
     *
     * @param  array{cz: Weapon, glock: Weapon, beretta: Weapon}  $weapons
     * @return list<array{days_ago: int, range_name: string, location: string, notes_raw: string, manual_reflection: ?string, shots: array, reflection: ?array}>
     */
    private function sessionsConfig(array $weapons): array
    {
        return [
            [
                'days_ago' => 2,
                'range_name' => 'KSV De Roos',
                'location' => 'Eindhoven',
                'notes_raw' => 'Goede dag, rustige ademhaling, focus op trekker.',
                'manual_reflection' => 'Iets te veel druk op de trekker bij de laatste serie.',
                'shots' => [
                    [$weapons['cz'], 25, 30, Deviation::HIGH, 'Strak gegroepeerd, lichte high tendens'],
                    [$weapons['beretta'], 25, 50, Deviation::NONE, 'Schone rooster, goede grouping'],
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
                    [$weapons['glock'], 15, 60, Deviation::LEFT, 'Patroon trekt links — grip checken'],
                ],
                'reflection' => [
                    'summary' => 'Snelvuur op 15m: lichte left-pull suggereert grip-correctie bij de Glock.',
                    'positives' => ['Tempo consistent', 'Geen flyers'],
                    'improvements' => ['Grip steviger op de support hand', 'Schouder lager houden'],
                    'next_focus' => 'Volgende sessie 30 schoten droog vuur voor grip-imprint.',
                ],
            ],
            [
                'days_ago' => 10,
                'range_name' => 'KSV De Roos',
                'location' => 'Eindhoven',
                'notes_raw' => 'Lange precisie-sessie op 25m.',
                'manual_reflection' => null,
                'shots' => [
                    [$weapons['cz'], 25, 40, Deviation::NONE, 'Mooi gecentreerd'],
                    [$weapons['beretta'], 25, 60, Deviation::LOW, 'Flyers in 4e serie, vermoeidheid'],
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
                    [$weapons['cz'], 25, 30, Deviation::RIGHT, 'Lichte right-pull, bredere grouping'],
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
                    [$weapons['glock'], 15, 50, Deviation::HIGH, 'Anticipatie na recoil zichtbaar'],
                    [$weapons['cz'], 25, 30, Deviation::NONE, 'Mooie groep, focus terug'],
                ],
                'reflection' => [
                    'summary' => 'Combinatie-sessie toont vermoeidheid na 60 Glock-schoten; 25m herstelt focus.',
                    'positives' => ['Switch precisie/snelvuur soepel', 'Eindgroep met CZ uitstekend'],
                    'improvements' => ['Pauze inbouwen na 50 schoten snelvuur', 'Pols-rekoefening tussendoor'],
                    'next_focus' => 'Stamina-training: 80 schoten Glock met geplande pauzes.',
                ],
            ],
        ];
    }
}
