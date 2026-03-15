<?php

namespace Database\Factories;

use App\Models\CoachQuestion;
use App\Models\Session;
use App\Models\User;
use App\Models\Weapon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoachQuestion>
 */
class CoachQuestionFactory extends Factory
{
    protected $model = CoachQuestion::class;

    public function definition(): array
    {
        $questions = [
            'Hoe kan ik mijn groepering verbeteren?',
            'Wat is de beste techniek voor snelvuur?',
            'Hoe voorkom ik anticipatie op de terugslag?',
            'Welke oefeningen zijn goed voor precisie?',
            'Hoe verbeter ik mijn triggerdiscipline?',
        ];

        $periodFrom = $this->faker->dateTimeBetween('-2 months', '-1 month');
        $periodTo = $this->faker->dateTimeBetween($periodFrom, 'now');

        return [
            'user_id' => User::factory(),
            'session_id' => $this->faker->optional(50)->randomElement([null, Session::factory()]),
            'weapon_id' => $this->faker->optional(70)->randomElement([null, Weapon::factory()]),
            'question' => $this->faker->randomElement($questions),
            'answer' => $this->faker->optional(80)->paragraph(),
            'asked_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
        ];
    }
}
