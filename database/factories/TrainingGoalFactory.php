<?php

namespace Database\Factories;

use App\Enums\TrainingGoalSource;
use App\Models\TrainingGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingGoal>
 */
class TrainingGoalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => rtrim($this->faker->sentence(4), '.'),
            'detail' => $this->faker->optional()->sentence(),
            'source' => TrainingGoalSource::Manual,
            'target_month' => now()->format('Y-m'),
            'session_id' => null,
            'weapon_id' => null,
            'completed_at' => null,
        ];
    }

    public function ai(): static
    {
        return $this->state(fn (): array => ['source' => TrainingGoalSource::Ai]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['completed_at' => now()]);
    }
}
