<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'site.name', 'value' => (string) config('cms.site.name'), 'type' => 'string', 'group' => 'general', 'is_public' => true],
            ['name' => 'site.description', 'value' => (string) config('cms.site.description'), 'type' => 'string', 'group' => 'general', 'is_public' => true],
            ['name' => 'site.posts_per_page', 'value' => (string) config('cms.site.posts_per_page'), 'type' => 'integer', 'group' => 'reading', 'is_public' => true],
            ['name' => 'site.tagline', 'value' => 'Publishing, simplified.', 'type' => 'string', 'group' => 'general', 'is_public' => true],
            ['name' => 'seo.default_title_suffix', 'value' => (string) config('cms.site.name'), 'type' => 'string', 'group' => 'seo', 'is_public' => true],
            ['name' => 'seo.default_description', 'value' => (string) config('cms.site.description'), 'type' => 'string', 'group' => 'seo', 'is_public' => true],
            ['name' => 'comments.require_moderation', 'value' => config('cms.comments.require_moderation') ? '1' : '0', 'type' => 'boolean', 'group' => 'discussion', 'is_public' => false],
            ['name' => 'users.allow_registration', 'value' => config('cms.site.allow_registration') ? '1' : '0', 'type' => 'boolean', 'group' => 'general', 'is_public' => true],
            ['name' => 'appearance.theme', 'value' => 'default', 'type' => 'string', 'group' => 'appearance', 'is_public' => true],
            ['name' => 'appearance.accent', 'value' => (string) config('cms.themes.default.accent', '#1d4ed8'), 'type' => 'string', 'group' => 'appearance', 'is_public' => true],
        ];

        foreach ($defaults as $setting) {
            Setting::query()->updateOrCreate(
                ['name' => $setting['name']],
                $setting,
            );
        }
    }
}
