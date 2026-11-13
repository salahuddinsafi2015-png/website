<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    protected function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_rich_text_widgets_are_sanitised_and_served_publicly(): void
    {
        $this->actingAsRole('editor');

        $this->postJson('/api/v1/admin/widgets', [
            'area' => 'footer',
            'type' => 'rich_text',
            'title' => 'About',
            'content' => '<p>Hello <strong>world</strong></p><script>alert(1)</script>',
        ])->assertCreated();

        $response = $this->getJson('/api/v1/widgets?area=footer')->assertOk();

        $text = (string) $response->json('data.0.text');

        $this->assertStringContainsString('Hello', $text);
        $this->assertStringNotContainsString('<script', $text);
    }

    public function test_inactive_widgets_are_hidden_and_order_is_preserved(): void
    {
        $this->actingAsRole('editor');

        $this->postJson('/api/v1/admin/widgets', [
            'area' => 'sidebar',
            'type' => 'recent_posts',
            'title' => 'Second',
            'sort_order' => 2,
        ])->assertCreated();

        $this->postJson('/api/v1/admin/widgets', [
            'area' => 'sidebar',
            'type' => 'categories',
            'title' => 'First',
            'sort_order' => 1,
        ])->assertCreated();

        $this->postJson('/api/v1/admin/widgets', [
            'area' => 'sidebar',
            'type' => 'categories',
            'title' => 'Hidden',
            'sort_order' => 3,
            'is_active' => false,
        ])->assertCreated();

        $this->getJson('/api/v1/widgets?area=sidebar')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'First')
            ->assertJsonPath('data.1.title', 'Second');
    }

    public function test_a_menu_widget_includes_the_menu_and_its_items(): void
    {
        $this->actingAsRole('editor');

        $menu = $this->postJson('/api/v1/admin/menus', [
            'name' => 'Footer',
            'location' => 'footer',
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", [
            'label' => 'All posts',
            'type' => 'custom',
            'url' => '/posts',
        ])->assertCreated();

        $this->postJson('/api/v1/admin/widgets', [
            'area' => 'footer',
            'type' => 'menu',
            'title' => 'Explore',
            'menu_id' => $menu['id'],
        ])->assertCreated();

        $this->getJson('/api/v1/widgets?area=footer')
            ->assertOk()
            ->assertJsonPath('data.0.menu.name', 'Footer')
            ->assertJsonPath('data.0.menu.items.0.url', '/posts');
    }

    public function test_a_menu_widget_requires_a_menu(): void
    {
        $this->actingAsRole('editor');

        $this->postJson('/api/v1/admin/widgets', [
            'area' => 'footer',
            'type' => 'menu',
            'title' => 'Broken',
        ])->assertStatus(422)->assertJsonValidationErrors('menu_id');
    }

    public function test_widgets_can_be_updated_and_deleted(): void
    {
        $this->actingAsRole('editor');

        $widget = $this->postJson('/api/v1/admin/widgets', [
            'area' => 'sidebar',
            'type' => 'categories',
            'title' => 'Browse',
        ])->assertCreated()->json('data');

        $this->patchJson("/api/v1/admin/widgets/{$widget['id']}", [
            'title' => 'Browse topics',
            'sort_order' => 3,
        ])->assertOk()->assertJsonPath('data.title', 'Browse topics');

        $this->deleteJson("/api/v1/admin/widgets/{$widget['id']}")->assertOk();

        $this->assertDatabaseCount('widgets', 0);
    }

    public function test_unknown_areas_are_rejected(): void
    {
        $this->actingAsRole('editor');

        $this->postJson('/api/v1/admin/widgets', [
            'area' => 'header',
            'type' => 'categories',
        ])->assertStatus(422)->assertJsonValidationErrors('area');

        $this->getJson('/api/v1/widgets?area=header')->assertStatus(422);
    }

    public function test_authors_cannot_manage_widgets(): void
    {
        $this->actingAsRole('author');

        $this->postJson('/api/v1/admin/widgets', [
            'area' => 'sidebar',
            'type' => 'categories',
            'title' => 'Nope',
        ])->assertForbidden();
    }

    public function test_widgets_can_be_reordered_in_bulk(): void
    {
        $this->actingAsRole('editor');

        $first = $this->postJson('/api/v1/admin/widgets', ['area' => 'sidebar', 'type' => 'categories', 'title' => 'First', 'sort_order' => 0])->assertCreated()->json('data');
        $second = $this->postJson('/api/v1/admin/widgets', ['area' => 'sidebar', 'type' => 'recent_posts', 'title' => 'Second', 'sort_order' => 1])->assertCreated()->json('data');
        $third = $this->postJson('/api/v1/admin/widgets', ['area' => 'sidebar', 'type' => 'recent_posts', 'title' => 'Third', 'sort_order' => 2])->assertCreated()->json('data');

        $this->putJson('/api/v1/admin/widgets/reorder', [
            'widgets' => [
                ['id' => $third['id'], 'sort_order' => 0],
                ['id' => $first['id'], 'sort_order' => 1],
                ['id' => $second['id'], 'sort_order' => 2],
            ],
        ])->assertOk()->assertJsonPath('data.0.title', 'Third');

        $this->getJson('/api/v1/widgets?area=sidebar')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Third')
            ->assertJsonPath('data.1.title', 'First')
            ->assertJsonPath('data.2.title', 'Second');
    }

    public function test_widget_reorder_rejects_unknown_widgets(): void
    {
        $this->actingAsRole('editor');

        $this->putJson('/api/v1/admin/widgets/reorder', [
            'widgets' => [['id' => 999, 'sort_order' => 0]],
        ])->assertStatus(422)->assertJsonValidationErrors('widgets.0.id');
    }

    public function test_authors_cannot_reorder_widgets(): void
    {
        $this->actingAsRole('author');

        $this->putJson('/api/v1/admin/widgets/reorder', [
            'widgets' => [['id' => 1, 'sort_order' => 0]],
        ])->assertForbidden();
    }

    public function test_menu_deletion_leaves_widget_intact(): void
    {
        $this->actingAsRole('editor');

        $menu = $this->postJson('/api/v1/admin/menus', ['name' => 'Temp'])->assertCreated()->json('data');

        $this->postJson('/api/v1/admin/widgets', [
            'area' => 'footer',
            'type' => 'menu',
            'title' => 'Temp menu',
            'menu_id' => $menu['id'],
        ])->assertCreated();

        $this->deleteJson("/api/v1/admin/menus/{$menu['id']}")->assertOk();

        $this->assertDatabaseCount('widgets', 1);
        $this->assertNull(Menu::first());
    }
}
