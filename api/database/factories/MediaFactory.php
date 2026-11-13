<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = fake()->word().'.jpg';

        return [
            'user_id' => User::factory(),
            'disk' => 'public',
            'path' => 'media/'.now()->format('Y/m').'/'.Str::ulid()->toBase32().'.jpg',
            'filename' => $filename,
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(1024, 900000),
            'width' => 1200,
            'height' => 630,
            'alt_text' => fake()->sentence(4),
        ];
    }

    public function document(): static
    {
        return $this->state(fn () => [
            'filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'width' => null,
            'height' => null,
        ]);
    }
}
