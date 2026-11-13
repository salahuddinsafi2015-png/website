<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SettingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
    }

    protected function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_administrators_can_switch_the_active_theme(): void
    {
        $this->actingAsRole('administrator');

        $this->getJson('/api/v1/admin/themes')
            ->assertOk()
            ->assertJsonPath('data.active', 'default')
            ->assertJsonCount(3, 'data.themes');

        $this->putJson('/api/v1/admin/themes', [
            'theme' => 'midnight',
            'accent' => '#101820',
        ])->assertOk()->assertJsonPath('data.theme', 'midnight');

        // The choice is persisted and exposed to the public site.
        $settings = $this->getJson('/api/v1/settings')->assertOk()->json('data');

        $this->assertSame('midnight', $settings['appearance.theme']);
        $this->assertSame('#101820', $settings['appearance.accent']);
    }

    public function test_switching_theme_without_accent_uses_the_theme_default(): void
    {
        $this->actingAsRole('administrator');

        $this->putJson('/api/v1/admin/themes', ['theme' => 'editorial'])
            ->assertOk()
            ->assertJsonPath('data.accent', config('cms.themes.editorial.accent'));
    }

    public function test_unknown_themes_and_bad_colours_are_rejected(): void
    {
        $this->actingAsRole('administrator');

        $this->putJson('/api/v1/admin/themes', ['theme' => 'neon'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme');

        $this->putJson('/api/v1/admin/themes', ['theme' => 'default', 'accent' => 'blue'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('accent');
    }

    public function test_editors_cannot_change_themes(): void
    {
        $this->actingAsRole('editor');

        $this->putJson('/api/v1/admin/themes', ['theme' => 'midnight'])->assertForbidden();
        $this->getJson('/api/v1/admin/themes')->assertForbidden();
    }

    public function test_guests_cannot_read_theme_settings(): void
    {
        $this->getJson('/api/v1/admin/themes')->assertUnauthorized();
    }
}
