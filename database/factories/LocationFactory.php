<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        $isRange = $this->faker->boolean(70); // 70% chance it's a range
        $isStorage = ! $isRange || $this->faker->boolean(30); // Can be both

        return [
            'user_id' => User::factory(),
            'name' => $isRange ? $this->faker->randomElement([
                'Schietvereniging De Schutte',
                'Shooting Range Amsterdam',
                'Schietcentrum Rotterdam',
                'Indoor Range Utrecht',
                'Outdoor Range Arnhem',
            ]) : $this->faker->randomElement([
                'Thuis - Kluis',
                'Garage - Wapenkluis',
                'Kelder - Beveiligde kast',
            ]),
            'is_range' => $isRange,
            'is_storage' => $isStorage,
            'notes' => $this->faker->optional(40)->sentence(),
        ];
    }

    public function range(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_range' => true,
            'name' => $this->faker->randomElement([
                'Schietvereniging De Schutte',
                'Shooting Range Amsterdam',
                'Schietcentrum Rotterdam',
            ]),
        ]);
    }

    public function storage(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_storage' => true,
            'is_range' => false,
            'name' => $this->faker->randomElement([
                'Thuis - Kluis',
                'Garage - Wapenkluis',
            ]),
        ]);
    }
}
