<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Deviation;
use App\Enums\WeaponType;
use App\Models\AiReflection;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\SessionWeapon;
use App\Models\User;
use App\Models\Weapon;
use App\Services\Demo\DemoShotGenerator;
use App\Support\DateFormat;
use Illuminate\Support\Carbon;
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
    public const WEAPON_COUNT = 3;

    public const SESSION_COUNT = 5;

    public const REFLECTION_COUNT = 3;

    /**
     * Som van rounds_fired over alle wapenregels in sessionsConfig(). De
     * demo-knop noemt dit getal, dus een test bewaakt dat het klopt met wat
     * de seeder werkelijk wegschrijft.
     */
    public const SHOT_COUNT = 350;

    /** Vaste basis-seed, zodat de demo-roos er voor iedere gebruiker hetzelfde uitziet. */
    private const SHOT_SEED = 20260808;

    /** Tijd tussen twee schoten; geeft de sessie een geloofwaardige duur en cadans. */
    private const SECONDS_BETWEEN_SHOTS = 42;

    /** Starttijd van een demo-sessie, in de weergave-tijdzone. */
    private const SESSION_START_TIME = '10:00:00';

    /**
     * Schrijven in blokken in plaats van één reusachtige insert: 350 schoten
     * maal elf kolommen gaat over de SQLite-limiet voor bind-parameters heen.
     */
    private const INSERT_CHUNK = 50;

    public function __construct(private readonly DemoShotGenerator $shotGenerator) {}

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
        foreach ($this->sessionsConfig($weapons) as $sessionIndex => $config) {
            $date = now()->subDays($config['days_ago'])->toDateString();

            $session = Session::query()->create([
                'user_id' => $user->id,
                'date' => $date,
                'range_name' => $config['range_name'],
                'location' => $config['location'],
                'notes_raw' => $config['notes_raw'],
                'manual_reflection' => $config['manual_reflection'],
            ]);

            foreach ($config['weapon_lines'] as [$weapon, $distance, $rounds, $deviation, $quality]) {
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

            $this->seedShots($session, $date, $sessionIndex, $config['weapon_lines']);

            if ($config['reflection']) {
                AiReflection::query()->create([
                    'session_id' => $session->id,
                    ...$config['reflection'],
                ]);
            }
        }
    }

    /**
     * Schrijft de echte SessionShot-rijen van één demo-sessie weg.
     *
     * Het aantal schoten is per wapenregel exact rounds_fired, zodat het
     * aggregaat op de wapenregel en de teller op de roos hetzelfde verhaal
     * vertellen. De turn_index telt door over de wapenregels heen, want de
     * unique index staat op (session_id, turn_index, shot_index).
     *
     * created_at loopt op per schot en start op de sessiedatum om 10:00 in de
     * WEERGAVE-tijdzone, omgerekend naar UTC. Dat laatste is geen detail: de
     * database staat in UTC, dus zonder die omrekening zou de demo-sessie een
     * of twee uur te vroeg beginnen. Gelijke timestamps zouden bovendien de
     * cadans- en duurberekening op null zetten (zie SessionStatsService::
     * avgCadansSec), en dan is het schotenbord alsnog half leeg.
     *
     * @param  array<int, array{0: Weapon, 1: int, 2: int, 3: Deviation, 4: string}>  $weaponLines
     */
    private function seedShots(Session $session, string $date, int $sessionIndex, array $weaponLines): void
    {
        $startedAt = Carbon::parse(
            $date.' '.self::SESSION_START_TIME,
            DateFormat::displayTimezone(),
        )->utc();

        $rows = [];
        $turnOffset = 0;
        $shotNumber = 0;

        foreach ($weaponLines as $lineIndex => [, , $rounds, $deviation]) {
            $shots = $this->shotGenerator->generate(
                rounds: $rounds,
                deviation: $deviation,
                seed: self::SHOT_SEED + $sessionIndex * 100 + $lineIndex,
                turnOffset: $turnOffset,
            );

            foreach ($shots as $shot) {
                $firedAt = $startedAt->copy()->addSeconds(self::SECONDS_BETWEEN_SHOTS * $shotNumber);
                $shotNumber++;

                $rows[] = [
                    'session_id' => $session->id,
                    ...$shot,
                    'metadata' => json_encode(['source' => 'demo']),
                    'created_at' => $firedAt,
                    'updated_at' => $firedAt,
                ];
            }

            $turnOffset += $this->shotGenerator->turnsFor($rounds);
        }

        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            SessionShot::query()->insert($chunk);
        }
    }

    /**
     * 5 sessies met 3 AI-reflecties — zodat de AI-coach drempel direct
     * unlocked is (3 ≥ 3 sessies) én de reflectie-UI gevuld lijkt.
     *
     * De sleutel heet weapon_lines en niet shots: dit zijn wapenregels met
     * een rounds_fired-getal, geen schoten. De echte SessionShot-rijen worden
     * daaruit afgeleid in seedShots().
     *
     * @param  array{cz: Weapon, glock: Weapon, beretta: Weapon}  $weapons
     * @return list<array{days_ago: int, range_name: string, location: string, notes_raw: string, manual_reflection: ?string, weapon_lines: array, reflection: ?array}>
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
                'weapon_lines' => [
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
                'weapon_lines' => [
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
                'weapon_lines' => [
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
                'weapon_lines' => [
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
                'weapon_lines' => [
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
