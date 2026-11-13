<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_administrators_can_update_settings_in_bulk(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/settings', [
            'settings' => [
                'site.name' => ['value' => 'Acme Publishing', 'group' => 'general', 'is_public' => true],
                'site.posts_per_page' => ['value' => 12, 'type' => 'integer', 'group' => 'reading', 'is_public' => true],
            ],
        ])->assertOk();

        $this->assertSame('Acme Publishing', Setting::get('site.name'));
        $this->assertSame(12, Setting::get('site.posts_per_page'));
    }

    public function test_editors_cannot_update_settings(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('editor');
        Sanctum::actingAs($editor);

        $this->putJson('/api/v1/admin/settings', [
            'settings' => ['site.name' => ['value' => 'Hijacked']],
        ])->assertForbidden();
    }

    public function test_administrators_can_create_users_with_roles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/users', [
            'name' => 'New Editor',
            'email' => 'new-editor@example.com',
            'password' => 'StrongPassword123',
            'roles' => ['editor'],
        ])->assertCreated()->assertJsonPath('data.roles.0', 'editor');

        $this->assertTrue(User::where('email', 'new-editor@example.com')->first()->hasRole('editor'));
    }

    public function test_authorization_prevents_access_to_user_management(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('editor');
        Sanctum::actingAs($editor);

        $this->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_administrators_cannot_delete_themselves(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/admin/users/{$admin->getKey()}")->assertForbidden();
    }
}
