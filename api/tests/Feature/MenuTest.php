<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Post;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MenuTest extends TestCase
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

    protected function createMenu(array $attributes = []): array
    {
        return $this->postJson('/api/v1/admin/menus', array_merge([
            'name' => 'Primary',
            'location' => 'primary',
        ], $attributes))->assertCreated()->json('data');
    }

    public function test_editors_can_build_a_menu_and_the_public_endpoint_returns_it(): void
    {
        $this->actingAsRole('editor');

        $menu = $this->createMenu();

        $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", [
            'label' => 'All posts',
            'type' => 'custom',
            'url' => '/posts',
        ])->assertCreated();

        $this->getJson('/api/v1/menus/primary')
            ->assertOk()
            ->assertJsonPath('data.location', 'primary')
            ->assertJsonPath('data.items.0.label', 'All posts')
            ->assertJsonPath('data.items.0.url', '/posts');
    }

    public function test_links_to_posts_and_pages_resolve_to_site_paths(): void
    {
        $post = Post::factory()->published()->create(['slug' => 'hello-world']);
        $page = Post::factory()->page()->published()->create(['slug' => 'about']);

        $this->actingAsRole('editor');
        $menu = $this->createMenu();

        $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", [
            'label' => 'Hello',
            'type' => 'post',
            'linkable_id' => $post->getKey(),
        ])->assertCreated();

        $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", [
            'label' => 'About',
            'type' => 'page',
            'linkable_id' => $page->getKey(),
        ])->assertCreated();

        $this->getJson('/api/v1/menus/primary')
            ->assertOk()
            ->assertJsonPath('data.items.0.url', '/posts/hello-world')
            ->assertJsonPath('data.items.1.url', '/about');
    }

    public function test_category_items_resolve_to_category_paths(): void
    {
        $category = Term::factory()->create(['taxonomy' => 'category', 'slug' => 'news']);

        $this->actingAsRole('editor');
        $menu = $this->createMenu();

        $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", [
            'label' => 'News',
            'type' => 'category',
            'linkable_id' => $category->getKey(),
        ])->assertCreated();

        $this->getJson('/api/v1/menus/primary')
            ->assertOk()
            ->assertJsonPath('data.items.0.url', '/categories/news');
    }

    public function test_a_location_can_hold_only_one_menu(): void
    {
        $this->actingAsRole('editor');

        $this->createMenu();

        $this->postJson('/api/v1/admin/menus', [
            'name' => 'Duplicate',
            'location' => 'primary',
        ])->assertStatus(422)->assertJsonValidationErrors('location');
    }

    public function test_inactive_items_are_hidden_from_the_public_menu(): void
    {
        $this->actingAsRole('editor');
        $menu = $this->createMenu();

        $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", [
            'label' => 'Hidden',
            'type' => 'custom',
            'url' => '/hidden',
            'is_active' => false,
        ])->assertCreated();

        $this->getJson('/api/v1/menus/primary')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_items_can_be_updated_and_removed(): void
    {
        $this->actingAsRole('editor');
        $menu = $this->createMenu();

        $item = $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", [
            'label' => 'Draft label',
            'type' => 'custom',
            'url' => '/posts',
        ])->assertCreated()->json('data');

        $this->patchJson("/api/v1/admin/menu-items/{$item['id']}", [
            'label' => 'Renamed',
            'sort_order' => 5,
        ])->assertOk()->assertJsonPath('data.label', 'Renamed')->assertJsonPath('data.sort_order', 5);

        $this->deleteJson("/api/v1/admin/menu-items/{$item['id']}")->assertOk();

        $this->assertDatabaseCount('menu_items', 0);
    }

    public function test_deleting_a_menu_removes_its_items(): void
    {
        $this->actingAsRole('editor');
        $menu = $this->createMenu();

        $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", [
            'label' => 'All posts',
            'type' => 'custom',
            'url' => '/posts',
        ])->assertCreated();

        $this->deleteJson("/api/v1/admin/menus/{$menu['id']}")->assertOk();

        $this->assertDatabaseCount('menu_items', 0);
    }

    public function test_custom_links_require_a_url(): void
    {
        $this->actingAsRole('editor');
        $menu = $this->createMenu();

        $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", [
            'label' => 'Broken',
            'type' => 'custom',
        ])->assertStatus(422)->assertJsonValidationErrors('url');
    }

    public function test_authors_cannot_manage_menus(): void
    {
        $this->actingAsRole('author');

        $this->postJson('/api/v1/admin/menus', ['name' => 'Nope'])->assertForbidden();
        $this->getJson('/api/v1/admin/menus')->assertForbidden();
    }

    public function test_guests_cannot_manage_menus(): void
    {
        $this->postJson('/api/v1/admin/menus', ['name' => 'Nope'])->assertUnauthorized();
    }

    public function test_unknown_locations_return_not_found(): void
    {
        $this->getJson('/api/v1/menus/nonexistent')->assertNotFound();
        $this->getJson('/api/v1/menus/primary')->assertNotFound();
    }

    public function test_items_can_be_reordered_in_bulk(): void
    {
        $this->actingAsRole('editor');
        $menu = $this->createMenu();

        $alpha = $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", ['label' => 'Alpha', 'type' => 'custom', 'url' => '/a'])->assertCreated()->json('data');
        $beta = $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", ['label' => 'Beta', 'type' => 'custom', 'url' => '/b'])->assertCreated()->json('data');
        $gamma = $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", ['label' => 'Gamma', 'type' => 'custom', 'url' => '/c'])->assertCreated()->json('data');

        $this->putJson("/api/v1/admin/menus/{$menu['id']}/reorder", [
            'items' => [
                ['id' => $gamma['id'], 'parent_id' => null, 'sort_order' => 0],
                ['id' => $alpha['id'], 'parent_id' => null, 'sort_order' => 1],
                ['id' => $beta['id'], 'parent_id' => null, 'sort_order' => 2],
            ],
        ])->assertOk()->assertJsonPath('data.items.0.label', 'Gamma');

        $this->getJson('/api/v1/menus/primary')
            ->assertOk()
            ->assertJsonPath('data.items.0.url', '/c')
            ->assertJsonPath('data.items.1.url', '/a')
            ->assertJsonPath('data.items.2.url', '/b');
    }

    public function test_reordered_children_keep_their_parent(): void
    {
        $this->actingAsRole('editor');
        $menu = $this->createMenu();

        $parent = $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", ['label' => 'Parent', 'type' => 'custom', 'url' => '/parent'])->assertCreated()->json('data');
        $first = $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", ['label' => 'First', 'type' => 'custom', 'url' => '/first', 'parent_id' => $parent['id']])->assertCreated()->json('data');
        $second = $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", ['label' => 'Second', 'type' => 'custom', 'url' => '/second', 'parent_id' => $parent['id']])->assertCreated()->json('data');

        $this->putJson("/api/v1/admin/menus/{$menu['id']}/reorder", [
            'items' => [
                ['id' => $second['id'], 'parent_id' => $parent['id'], 'sort_order' => 0],
                ['id' => $first['id'], 'parent_id' => $parent['id'], 'sort_order' => 1],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('menu_items', ['id' => $second['id'], 'parent_id' => $parent['id'], 'sort_order' => 0]);

        $this->getJson('/api/v1/menus/primary')
            ->assertOk()
            ->assertJsonPath('data.items.1.label', 'Second');
    }

    public function test_reorder_rejects_items_from_another_menu(): void
    {
        $this->actingAsRole('editor');
        $menu = $this->createMenu();

        $other = $this->postJson('/api/v1/admin/menus', ['name' => 'Other'])->assertCreated()->json('data');
        $item = $this->postJson("/api/v1/admin/menus/{$other['id']}/items", ['label' => 'Foreign', 'type' => 'custom', 'url' => '/foreign'])->assertCreated()->json('data');

        $this->putJson("/api/v1/admin/menus/{$menu['id']}/reorder", [
            'items' => [
                ['id' => $item['id'], 'parent_id' => null, 'sort_order' => 0],
            ],
        ])->assertStatus(422);
    }

    public function test_authors_cannot_reorder_menus(): void
    {
        $this->actingAsRole('editor');
        $menu = $this->createMenu();

        $this->actingAsRole('author');

        $this->putJson("/api/v1/admin/menus/{$menu['id']}/reorder", [
            'items' => [['id' => 1, 'parent_id' => null, 'sort_order' => 0]],
        ])->assertForbidden();
    }

    public function test_reorder_can_move_an_item_between_parents(): void
    {
        $this->actingAsRole('editor');
        $menu = $this->createMenu();

        $alpha = $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", ['label' => 'Alpha', 'type' => 'custom', 'url' => '/a', 'sort_order' => 10])->assertCreated()->json('data');
        $beta = $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", ['label' => 'Beta', 'type' => 'custom', 'url' => '/b', 'sort_order' => 20])->assertCreated()->json('data');
        $child = $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", ['label' => 'Child', 'type' => 'custom', 'url' => '/c', 'parent_id' => $alpha['id'], 'sort_order' => 11])->assertCreated()->json('data');

        $this->putJson("/api/v1/admin/menus/{$menu['id']}/reorder", [
            'items' => [
                ['id' => $child['id'], 'parent_id' => $beta['id'], 'sort_order' => 21],
            ],
        ])->assertOk();

        $this->getJson('/api/v1/menus/primary')
            ->assertOk()
            ->assertJsonPath('data.items.0.label', 'Alpha')
            ->assertJsonPath('data.items.1.label', 'Beta')
            ->assertJsonPath('data.items.2.label', 'Child')
            ->assertJsonPath('data.items.2.parent_id', $beta['id']);
    }

    public function test_reorder_rejects_cyclic_nesting(): void
    {
        $this->actingAsRole('editor');
        $menu = $this->createMenu();

        $parent = $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", ['label' => 'Parent', 'type' => 'custom', 'url' => '/parent', 'sort_order' => 0])->assertCreated()->json('data');
        $child = $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", ['label' => 'Child', 'type' => 'custom', 'url' => '/child', 'parent_id' => $parent['id'], 'sort_order' => 1])->assertCreated()->json('data');

        $this->putJson("/api/v1/admin/menus/{$menu['id']}/reorder", [
            'items' => [
                ['id' => $parent['id'], 'parent_id' => $child['id'], 'sort_order' => 0],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseHas('menu_items', ['id' => $parent['id'], 'parent_id' => null]);
    }

    public function test_updates_reject_cyclic_nesting(): void
    {
        $this->actingAsRole('editor');
        $menu = $this->createMenu();

        $parent = $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", ['label' => 'Parent', 'type' => 'custom', 'url' => '/parent'])->assertCreated()->json('data');
        $child = $this->postJson("/api/v1/admin/menus/{$menu['id']}/items", ['label' => 'Child', 'type' => 'custom', 'url' => '/child', 'parent_id' => $parent['id']])->assertCreated()->json('data');

        $this->patchJson("/api/v1/admin/menu-items/{$parent['id']}", [
            'label' => 'Parent',
            'type' => 'custom',
            'url' => '/parent',
            'parent_id' => $child['id'],
        ])->assertStatus(422);

        $this->assertDatabaseHas('menu_items', ['id' => $parent['id'], 'parent_id' => null]);
    }

    public function test_menus_expose_their_slug_for_admin_routing(): void
    {
        $this->actingAsRole('editor');
        $menu = $this->createMenu(['name' => 'Footer links', 'location' => null]);

        $this->getJson("/api/v1/admin/menus/{$menu['slug']}")
            ->assertOk()
            ->assertJsonPath('data.slug', 'footer-links');

        $this->assertNull(Menu::first()->location);
    }
}
