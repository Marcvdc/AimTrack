<?php

namespace Database\Factories;

use App\Models\AiReflection;
use App\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiReflection>
 */
class AiReflectionFactory extends Factory
{
    protected $model = AiReflection::class;

    public function definition(): array
    {
        return [
            'session_id' => Session::factory(),
            'summary' => $this->faker->sentence(),
            'positives' => [
                $this->faker->sentence(4),
            ],
            'improvements' => [
                $this->faker->sentence(4),
            ],
            'next_focus' => $this->faker->sentence(6),
        ];
    }
}
