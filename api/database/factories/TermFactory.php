<?php

namespace Database\Factories;

use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Term>
 */
class TermFactory extends Factory
{
    protected $model = Term::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = rtrim(fake()->unique()->words(2, true), '.');

        return [
            'taxonomy' => 'category',
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(8),
        ];
    }

    public function tag(): static
    {
        return $this->state(fn () => ['taxonomy' => 'tag']);
    }
}
