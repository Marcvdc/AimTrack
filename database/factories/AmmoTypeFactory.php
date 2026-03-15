<?php

namespace Database\Factories;

use App\Models\AmmoType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AmmoType>
 */
class AmmoTypeFactory extends Factory
{
    protected $model = AmmoType::class;

    public function definition(): array
    {
        $caliber = $this->faker->randomElement(['9mm', '.22LR', '.45 ACP', '.40 S&W', '5.56mm']);
        $brands = ['Federal', 'Winchester', 'Sellier & Bellot', 'Fiocchi', 'Magtech'];

        return [
            'user_id' => User::factory(),
            'name' => $this->faker->randomElement($brands).' '.$caliber.' FMJ',
            'caliber' => $caliber,
            'notes' => $this->faker->optional(30)->sentence(),
        ];
    }
}
