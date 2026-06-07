<?php

namespace Database\Factories;

use App\Enums\WeaponType;
use App\Models\User;
use App\Models\Weapon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Weapon>
 */
class WeaponFactory extends Factory
{
    protected $model = Weapon::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->randomElement(['CZ Shadow 2', 'Glock 17', 'SIG P320']),
            'weapon_type' => WeaponType::PISTOL,
            'caliber' => $this->faker->randomElement(['9mm', '.22LR']),
            'serial_number' => strtoupper($this->faker->bothify('SN###??')),
            'storage_location' => $this->faker->city(),
            'owned_since' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'is_active' => true,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
