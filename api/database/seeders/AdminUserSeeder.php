<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('CMS_ADMIN_EMAIL', 'admin@example.com');
        $password = (string) env('CMS_ADMIN_PASSWORD', 'ChangeMe-2026!');

        /** @var User $admin */
        $admin = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Site Administrator',
                'display_name' => 'Administrator',
                'username' => 'admin',
                'password' => $password,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ],
        );

        $admin->syncRoles(['administrator']);

        $this->command?->warn(
            "Administrator ready: {$email}. Change the password immediately if this is not a local environment."
        );
    }
}
