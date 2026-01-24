<?php

namespace Database\Factories;

use App\Models\Session;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Session>
 */
class SessionFactory extends Factory
{
    protected $model = Session::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'range_name' => $this->faker->company(),
            'location' => $this->faker->city(),
            'notes_raw' => $this->faker->optional()->sentence(),
            'manual_reflection' => $this->faker->optional()->sentence(),
        ];
    }
}
