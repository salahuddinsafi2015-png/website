<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Model events are intentionally enabled: slug generation and publishing
     * timestamps live in model lifecycle hooks.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingSeeder::class,
            AdminUserSeeder::class,
            DemoContentSeeder::class,
        ]);
    }
}
