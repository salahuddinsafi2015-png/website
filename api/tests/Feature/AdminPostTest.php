<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_contributors_can_create_drafts(): void
    {
        Sanctum::actingAs($this->userWithRole('contributor'));

        $response = $this->postJson('/api/v1/admin/posts', [
            'title' => 'My draft',
            'content' => '<p>Draft body</p>',
            'status' => 'draft',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.slug', 'my-draft');

        $this->assertDatabaseHas('posts', ['title' => 'My draft', 'status' => 'draft']);
        // A revision is captured for every create.
        $this->assertDatabaseCount('post_revisions', 1);
    }

    public function test_contributors_cannot_publish(): void
    {
        Sanctum::actingAs($this->userWithRole('contributor'));

        $this->postJson('/api/v1/admin/posts', [
            'title' => 'Sneaky publish',
            'status' => 'publish',
        ])->assertForbidden();
    }

    public function test_authors_can_publish_their_own_posts(): void
    {
        $author = $this->userWithRole('author');
        Sanctum::actingAs($author);

        $this->postJson('/api/v1/admin/posts', [
            'title' => 'Shipping today',
            'status' => 'publish',
            'content' => '<p>Live</p>',
        ])->assertCreated()->assertJsonPath('data.status', 'publish');

        $this->assertNotNull(Post::first()->published_at);
    }

    public function test_authors_cannot_edit_other_peoples_posts(): void
    {
        $author = $this->userWithRole('author');
        $post = Post::factory()->create(['title' => 'Owned by someone else']);

        Sanctum::actingAs($author);

        $this->patchJson("/api/v1/admin/posts/{$post->getKey()}", [
            'title' => 'Hijacked',
        ])->assertForbidden();
    }

    public function test_editors_can_edit_any_post_and_a_revision_is_recorded(): void
    {
        $editor = $this->userWithRole('editor');
        $post = Post::factory()->create(['title' => 'Original title', 'status' => 'draft']);

        Sanctum::actingAs($editor);

        $this->patchJson("/api/v1/admin/posts/{$post->getKey()}", [
            'title' => 'Edited title',
        ])->assertOk()->assertJsonPath('data.title', 'Edited title');

        $this->assertDatabaseCount('post_revisions', 1);
        $this->assertDatabaseHas('post_revisions', ['title' => 'Original title']);
    }

    public function test_slugs_are_unique_per_post_type(): void
    {
        $editor = $this->userWithRole('editor');
        Sanctum::actingAs($editor);

        $this->postJson('/api/v1/admin/posts', ['title' => 'Same Title', 'status' => 'draft'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'same-title');

        $this->postJson('/api/v1/admin/posts', ['title' => 'Same Title', 'status' => 'draft'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'same-title-2');

        // A page may reuse the slug because uniqueness is scoped to the type.
        $this->postJson('/api/v1/admin/posts', ['title' => 'Same Title', 'type' => 'page', 'status' => 'draft'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'same-title');
    }

    public function test_content_is_sanitised_before_storage(): void
    {
        Sanctum::actingAs($this->userWithRole('editor'));

        $response = $this->postJson('/api/v1/admin/posts', [
            'title' => 'XSS attempt',
            'status' => 'draft',
            'content' => '<p>Safe copy</p><script>alert("xss")</script><img src=x onerror=alert(1)>',
        ])->assertCreated();

        $content = (string) $response->json('data.content');

        $this->assertStringNotContainsString('<script', $content);
        $this->assertStringNotContainsString('onerror', $content);
        $this->assertStringContainsString('Safe copy', $content);
    }

    public function test_terms_are_attached_and_counts_updated(): void
    {
        $editor = $this->userWithRole('editor');
        $category = Term::factory()->create(['slug' => 'news']);
        Sanctum::actingAs($editor);

        $this->postJson('/api/v1/admin/posts', [
            'title' => 'Filed under news',
            'status' => 'publish',
            'term_ids' => [$category->getKey()],
        ])->assertCreated();

        $this->assertDatabaseHas('post_term', ['term_id' => $category->getKey()]);
        $this->assertSame(1, $category->refresh()->count);
    }

    public function test_posts_move_to_trash_and_back(): void
    {
        $editor = $this->userWithRole('editor');
        $post = Post::factory()->published()->create();
        Sanctum::actingAs($editor);

        $this->deleteJson("/api/v1/admin/posts/{$post->getKey()}")->assertOk();
        $this->assertSoftDeleted($post);
        $this->assertDatabaseHas('posts', ['id' => $post->getKey(), 'status' => 'trash']);

        $this->postJson("/api/v1/admin/posts/{$post->getKey()}/restore")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertNotSoftDeleted($post);
    }

    public function test_guests_cannot_touch_the_admin_api(): void
    {
        $this->postJson('/api/v1/admin/posts', ['title' => 'Nope'])->assertUnauthorized();
    }

    public function test_dashboard_reports_content_statistics(): void
    {
        Sanctum::actingAs($this->userWithRole('editor'));

        Post::factory()->published()->count(2)->create();
        Post::factory()->count(3)->create();

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.posts', 5)
            ->assertJsonPath('data.stats.published', 2)
            ->assertJsonPath('data.stats.drafts', 3);
    }
}
