<?php

namespace Database\Factories;

use App\Models\Session;
use App\Models\SessionShot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionShot>
 */
class SessionShotFactory extends Factory
{
    protected $model = SessionShot::class;

    public function definition(): array
    {
        return [
            'session_id' => Session::factory(),
            'turn_index' => $this->faker->numberBetween(0, 5),
            'shot_index' => $this->faker->numberBetween(0, 9),
            'x_normalized' => $this->faker->randomFloat(5, 0, 1),
            'y_normalized' => $this->faker->randomFloat(5, 0, 1),
            'distance_from_center' => $this->faker->randomFloat(5, 0, 0.7),
            'ring' => $this->faker->numberBetween(5, 10),
            'score' => $this->faker->numberBetween(5, 10),
            'metadata' => null,
        ];
    }
}
