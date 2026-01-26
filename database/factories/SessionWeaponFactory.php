<?php

namespace Database\Factories;

use App\Enums\Deviation;
use App\Models\Session;
use App\Models\SessionWeapon;
use App\Models\Weapon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionWeapon>
 */
class SessionWeaponFactory extends Factory
{
    protected $model = SessionWeapon::class;

    public function definition(): array
    {
        return [
            'session_id' => Session::factory(),
            'weapon_id' => Weapon::factory(),
            'distance_m' => $this->faker->randomElement([10, 25, 50]),
            'rounds_fired' => $this->faker->numberBetween(10, 60),
            'ammo_type' => $this->faker->randomElement(['9mm FMG', '.22LR club', '147gr subsonic']),
            'group_quality_text' => $this->faker->sentence(4),
            'deviation' => $this->faker->randomElement(array_column(Deviation::cases(), 'value')),
            'flyers_count' => $this->faker->numberBetween(0, 3),
        ];
    }
}
