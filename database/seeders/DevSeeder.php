<?php

namespace Database\Seeders;

use App\Enums\Deviation;
use App\Enums\WeaponType;
use App\Models\AiReflection;
use App\Models\AmmoType;
use App\Models\Location;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\SessionWeapon;
use App\Models\User;
use App\Models\Weapon;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Testdata aanmaken...');

        $mainUser = $this->createMainUser();
        $secondUser = $this->createSecondUser();

        $this->seedUserData($mainUser, sessions: 22, label: 'Hoofdgebruiker');
        $this->seedUserData($secondUser, sessions: 8, label: 'Tweede gebruiker');

        $this->command->info('Klaar! Inloggen als test@aimtrack.nl / password');
    }

    private function createMainUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'test@aimtrack.nl'],
            [
                'name' => 'Lars van den Berg',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }

    private function createSecondUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'marie@aimtrack.nl'],
            [
                'name' => 'Marie de Vries',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }

    private function seedUserData(User $user, int $sessions, string $label): void
    {
        $this->command->info("  {$label}: locaties, wapens en munitie aanmaken...");

        $storageLocation = Location::factory()->storage()->create(['user_id' => $user->id]);
        $rangeLocation1 = Location::factory()->range()->create([
            'user_id' => $user->id,
            'name' => 'Schietvereniging De Pijl',
        ]);
        $rangeLocation2 = Location::factory()->range()->create([
            'user_id' => $user->id,
            'name' => 'Indoor Schietbaan Utrecht',
        ]);

        $ammo9mm = AmmoType::factory()->create([
            'user_id' => $user->id,
            'name' => 'Sellier & Bellot 9mm 115gr FMJ',
            'caliber' => '9mm',
        ]);
        $ammo22 = AmmoType::factory()->create([
            'user_id' => $user->id,
            'name' => 'Eley .22LR Club',
            'caliber' => '.22LR',
        ]);
        AmmoType::factory()->create([
            'user_id' => $user->id,
            'name' => 'Fiocchi 9mm 147gr Subsonic',
            'caliber' => '9mm',
        ]);

        $pistol = Weapon::factory()->create([
            'user_id' => $user->id,
            'name' => 'CZ Shadow 2',
            'weapon_type' => WeaponType::PISTOL,
            'caliber' => '9mm',
            'storage_location_id' => $storageLocation->id,
        ]);
        $rifle = Weapon::factory()->create([
            'user_id' => $user->id,
            'name' => 'Ruger 10/22',
            'weapon_type' => WeaponType::RIFLE,
            'caliber' => '.22LR',
            'storage_location_id' => $storageLocation->id,
        ]);

        $this->command->info("  {$label}: {$sessions} sessies aanmaken...");

        $ranges = [$rangeLocation1, $rangeLocation2];
        $weapons = [$pistol, $rifle];
        $ammoByCalibr = ['9mm' => $ammo9mm, '.22LR' => $ammo22];

        for ($i = $sessions; $i >= 1; $i--) {
            $daysAgo = (int) ($i * (180 / $sessions)) + rand(-3, 3);
            $sessionDate = Carbon::now()->subDays(max(1, $daysAgo));
            $rangeLocation = $ranges[array_rand($ranges)];

            $session = Session::factory()->create([
                'user_id' => $user->id,
                'date' => $sessionDate,
                'range_name' => $rangeLocation->name,
                'location_id' => $rangeLocation->id,
                'range_location_id' => $rangeLocation->id,
                'notes_raw' => fake()->optional(60)->sentence(10),
            ]);

            $sessionWeaponCount = rand(1, 2);
            $sessionWeapons = array_slice($weapons, 0, $sessionWeaponCount);
            $quality = $this->sessionQuality($i, $sessions);

            foreach ($sessionWeapons as $weapon) {
                $ammoType = $ammoByCalibr[$weapon->caliber] ?? null;

                SessionWeapon::factory()->create([
                    'session_id' => $session->id,
                    'weapon_id' => $weapon->id,
                    'distance_m' => $weapon->weapon_type === WeaponType::PISTOL
                        ? fake()->randomElement([10, 25])
                        : fake()->randomElement([25, 50]),
                    'rounds_fired' => rand(20, 60),
                    'ammo_type_id' => $ammoType?->id,
                    'ammo_type' => $ammoType?->name,
                    'deviation' => $this->deviationForQuality($quality),
                    'flyers_count' => $quality === 'good' ? rand(0, 1) : rand(0, 3),
                    'group_quality_text' => $this->groupQualityText($quality),
                ]);
            }

            $this->createShots($session, $quality, shotCount: rand(20, 40));

            if (fake()->boolean(70)) {
                $this->createAiReflection($session);
            }
        }
    }

    private function sessionQuality(int $sessionNumber, int $total): string
    {
        $progress = $sessionNumber / $total;
        $rand = rand(1, 100);

        if ($progress < 0.33) {
            return $rand <= 30 ? 'good' : ($rand <= 60 ? 'average' : 'poor');
        } elseif ($progress < 0.66) {
            return $rand <= 50 ? 'good' : ($rand <= 80 ? 'average' : 'poor');
        } else {
            return $rand <= 65 ? 'good' : ($rand <= 90 ? 'average' : 'poor');
        }
    }

    private function deviationForQuality(string $quality): string
    {
        if ($quality === 'good') {
            return Deviation::NONE->value;
        }

        $deviations = array_column(Deviation::cases(), 'value');

        return fake()->randomElement($deviations);
    }

    private function groupQualityText(string $quality): string
    {
        return match ($quality) {
            'good' => fake()->randomElement([
                'Strakke groep, consistent resultaat.',
                'Alle schoten goed geplaatst, weinig spreiding.',
                'Goede focus en ademhalingstechniek toegepast.',
            ]),
            'average' => fake()->randomElement([
                'Redelijke groep met wat spreiding rechts.',
                'Meeste schoten goed, enkele uitschieters.',
                'Consistentie wisselend, maar acceptabel resultaat.',
            ]),
            default => fake()->randomElement([
                'Grote spreiding, techniek loopt niet lekker.',
                'Trekbewegingen zichtbaar in de groep.',
                'Veel uitschieters, focus en houding verbeteren.',
            ]),
        };
    }

    private function createShots(Session $session, string $quality, int $shotCount): void
    {
        $spread = match ($quality) {
            'good' => 0.06,
            'average' => 0.13,
            default => 0.22,
        };

        $offsetX = match ($quality) {
            'good' => 0.0,
            'average' => fake()->randomFloat(3, -0.05, 0.05),
            default => fake()->randomFloat(3, -0.10, 0.10),
        };
        $offsetY = match ($quality) {
            'good' => 0.0,
            'average' => fake()->randomFloat(3, -0.05, 0.05),
            default => fake()->randomFloat(3, -0.10, 0.10),
        };

        $shots = [];

        for ($i = 0; $i < $shotCount; $i++) {
            $angle = lcg_value() * 2 * M_PI;
            $radius = abs($this->gaussianRandom(0, $spread));

            $x = max(0.0, min(1.0, 0.5 + $offsetX + $radius * cos($angle)));
            $y = max(0.0, min(1.0, 0.5 + $offsetY + $radius * sin($angle)));

            $distanceFromCenter = sqrt(($x - 0.5) ** 2 + ($y - 0.5) ** 2);
            [$ring, $score] = $this->ringAndScoreFromDistance($distanceFromCenter);

            $shots[] = [
                'session_id' => $session->id,
                'turn_index' => (int) floor($i / 10),
                'shot_index' => $i % 10,
                'x_normalized' => round($x, 5),
                'y_normalized' => round($y, 5),
                'distance_from_center' => round($distanceFromCenter, 5),
                'ring' => $ring,
                'score' => $score,
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        SessionShot::insert($shots);
    }

    /** @return array{int, int} */
    private function ringAndScoreFromDistance(float $distance): array
    {
        $ring = match (true) {
            $distance < 0.04 => 10,
            $distance < 0.08 => 9,
            $distance < 0.12 => 8,
            $distance < 0.16 => 7,
            $distance < 0.20 => 6,
            $distance < 0.27 => 5,
            $distance < 0.35 => 4,
            default => 3,
        };

        return [$ring, $ring];
    }

    private function gaussianRandom(float $mean, float $stdDev): float
    {
        $u = lcg_value();
        $v = lcg_value();

        return $mean + $stdDev * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }

    private function createAiReflection(Session $session): void
    {
        $positives = [
            fake()->randomElement([
                'Goede ademhalingstechniek toegepast.',
                'Stabiele schiethouding gedurende de hele sessie.',
                'Consistente trekkerbeweging zonder trekken.',
                'Focus op het voorkorrel goed vastgehouden.',
            ]),
            fake()->randomElement([
                'Snelle herstelbeweging na elk schot.',
                'Mentale focus was sterk aanwezig.',
                'Tempo van schieten goed beheerst.',
            ]),
        ];

        $improvements = [
            fake()->randomElement([
                'Meer aandacht besteden aan de ademhalingsmoment.',
                'Trekkerbeheersing kan consistenter.',
                'Follow-through na het schot verbeteren.',
                'Houding links iets corrigeren voor minder spreiding.',
            ]),
            fake()->randomElement([
                'Meer rust nemen tussen de series door.',
                'Concentratie in de laatste serie liep weg.',
            ]),
        ];

        AiReflection::factory()->create([
            'session_id' => $session->id,
            'summary' => fake()->sentences(2, true),
            'positives' => $positives,
            'improvements' => $improvements,
            'next_focus' => fake()->randomElement([
                'Werk aan een consistente ademhalingsroutine voor elk schot.',
                'Oefenen met dry-fire om trekkerbeheersing te verbeteren.',
                'Focus op follow-through na elk schot.',
                'Verhoog de afstand geleidelijk om techniek te testen.',
            ]),
        ]);
    }
}
