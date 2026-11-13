<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostRevision>
 */
class PostRevisionFactory extends Factory
{
    protected $model = PostRevision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'author_id' => null,
            'title' => fake()->sentence(5),
            'excerpt' => fake()->sentence(10),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'status' => 'draft',
        ];
    }
}
