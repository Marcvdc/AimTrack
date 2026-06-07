<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        $mimeType = $this->faker->randomElement(['image/jpeg', 'image/png', 'application/pdf']);
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => 'jpg',
        };

        return [
            'session_id' => Session::factory(),
            'path' => 'attachments/'.$this->faker->uuid().'.'.$extension,
            'original_name' => $this->faker->word().'.'.$extension,
            'mime_type' => $mimeType,
            'size' => $this->faker->numberBetween(10000, 5000000), // 10KB to 5MB
        ];
    }
}
