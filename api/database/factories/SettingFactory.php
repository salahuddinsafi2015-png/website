<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(3).'.'.fake()->word(),
            'value' => fake()->sentence(4),
            'type' => 'string',
            'group' => 'general',
            'is_public' => false,
        ];
    }
}
