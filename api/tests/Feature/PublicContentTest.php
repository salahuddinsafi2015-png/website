<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Setting;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_posts_are_listed(): void
    {
        Post::factory()->published()->create(['title' => 'Live post', 'slug' => 'live-post']);
        Post::factory()->create(['title' => 'Draft post', 'slug' => 'draft-post']);
        Post::factory()->scheduled()->create(['title' => 'Scheduled post', 'slug' => 'scheduled-post']);

        $response = $this->getJson('/api/v1/posts');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'live-post');
    }

    public function test_a_published_post_can_be_fetched_by_slug(): void
    {
        Post::factory()->published()->create([
            'title' => 'Hello world',
            'slug' => 'hello-world',
            'content' => '<p>Body copy</p>',
        ]);

        $this->getJson('/api/v1/posts/hello-world')
            ->assertOk()
            ->assertJsonPath('data.title', 'Hello world')
            ->assertJsonPath('data.content', '<p>Body copy</p>')
            ->assertJsonPath('data.path', '/posts/hello-world');
    }

    public function test_draft_posts_are_not_publicly_readable(): void
    {
        Post::factory()->create(['slug' => 'secret-draft']);

        $this->getJson('/api/v1/posts/secret-draft')->assertNotFound();
    }

    public function test_a_scheduled_post_is_hidden_until_its_date(): void
    {
        Post::factory()->create([
            'slug' => 'later',
            'status' => 'publish',
            'published_at' => now()->addDay(),
        ]);

        $this->getJson('/api/v1/posts/later')->assertNotFound();
    }

    public function test_password_protected_posts_require_the_password(): void
    {
        Post::factory()->published()->create([
            'slug' => 'vault',
            'password' => Hash::make('open-sesame'),
        ]);

        $this->getJson('/api/v1/posts/vault')->assertForbidden();

        $this->getJson('/api/v1/posts/vault?password=open-sesame')
            ->assertOk()
            ->assertJsonPath('data.is_password_protected', true);
    }

    public function test_posts_can_be_filtered_by_category(): void
    {
        $category = Term::factory()->create(['name' => 'Engineering', 'slug' => 'engineering']);

        $matching = Post::factory()->published()->create(['slug' => 'in-category']);
        $matching->terms()->attach($category);

        Post::factory()->published()->create(['slug' => 'elsewhere']);

        $this->getJson('/api/v1/posts?category=engineering')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'in-category');
    }

    public function test_posts_can_be_searched(): void
    {
        Post::factory()->published()->create(['title' => 'Tuning MySQL indexes', 'slug' => 'mysql']);
        Post::factory()->published()->create(['title' => 'Designing layouts', 'slug' => 'layouts']);

        $this->getJson('/api/v1/posts?search=MySQL')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'mysql');
    }

    public function test_terms_endpoint_returns_categories(): void
    {
        Term::factory()->create(['name' => 'Guides', 'slug' => 'guides']);

        $this->getJson('/api/v1/terms?taxonomy=category')
            ->assertOk()
            ->assertJsonPath('data.0.taxonomy', 'category');
    }

    public function test_only_public_settings_are_exposed(): void
    {
        Setting::factory()->create([
            'name' => 'site.name',
            'value' => 'My Site',
            'group' => 'general',
            'is_public' => true,
        ]);

        Setting::factory()->create([
            'name' => 'secret.api_key',
            'value' => 'super-secret',
            'group' => 'internal',
            'is_public' => false,
        ]);

        // Setting names contain dots, so they are returned as flat keys
        // rather than nested objects.
        $data = $this->getJson('/api/v1/settings')->assertOk()->json('data');

        $this->assertSame('My Site', $data['site.name']);
        $this->assertArrayNotHasKey('secret.api_key', $data);
    }
}
