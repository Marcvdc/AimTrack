<?php

namespace Database\Factories;

use App\Models\Vereniging;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Vereniging>
 */
class VerenigingFactory extends Factory
{
    protected $model = Vereniging::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $naam = 'SV '.$this->faker->unique()->city();

        return [
            'naam' => $naam,
            'slug' => Str::slug($naam).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'anthropic_api_key' => null,
            'ai_key_verified_at' => null,
            'settings' => null,
            'created_by' => null,
        ];
    }

    public function withKey(string $key = 'sk-ant-vereniging'): static
    {
        return $this->state(fn (): array => [
            'anthropic_api_key' => $key,
            'ai_key_verified_at' => now(),
        ]);
    }
}
