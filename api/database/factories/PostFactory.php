<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(6), '.');

        return [
            'author_id' => User::factory(),
            'type' => 'post',
            'status' => PostStatus::Draft,
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(12),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'content_format' => 'html',
            'comment_status' => 'open',
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function page(): static
    {
        return $this->state(fn () => ['type' => 'page']);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Future,
            'published_at' => now()->addWeek(),
        ]);
    }
}
