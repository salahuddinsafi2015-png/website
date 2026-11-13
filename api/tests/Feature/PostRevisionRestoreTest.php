<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostRevisionRestoreTest extends TestCase
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

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function sampleBlocks(): array
    {
        return [
            ['type' => 'heading', 'level' => 2, 'text' => 'Original heading'],
            ['type' => 'paragraph', 'text' => '<script>alert(1)</script> A normal sentence.'],
            ['type' => 'divider'],
        ];
    }

    public function test_restoring_a_revision_rolls_back_title_content_and_blocks(): void
    {
        Sanctum::actingAs($this->userWithRole('editor'));

        $created = $this->postJson('/api/v1/admin/posts', [
            'title' => 'Original title',
            'status' => 'draft',
            'blocks' => $this->sampleBlocks(),
        ])->assertCreated();

        $postId = $created->json('data.id');
        $originalRevisionId = $created->json('data.revisions.0.id')
            ?? PostRevision::query()->where('post_id', $postId)->latest('id')->first()->id;

        $this->putJson("/api/v1/admin/posts/{$postId}", [
            'title' => 'Edited title',
            'status' => 'draft',
            'blocks' => [
                ['type' => 'paragraph', 'text' => 'A completely different body.'],
            ],
        ])->assertOk();

        $this->postJson("/api/v1/admin/posts/{$postId}/revisions/{$originalRevisionId}/restore")
            ->assertOk()
            ->assertJsonPath('data.title', 'Original title')
            ->assertJsonPath('data.content_format', 'blocks');

        $post = Post::find($postId);
        $this->assertNotNull($post->blocks);
        $this->assertSame('heading', $post->blocks[0]['type']);
        $this->assertSame('Original heading', $post->blocks[0]['text']);
        $this->assertStringContainsString('&lt;script&gt;', (string) $post->content);
        // Status stays what it currently was: history restores content, not publication.
        $this->assertSame('draft', $post->status->value);
    }

    public function test_restore_is_itself_undoable(): void
    {
        Sanctum::actingAs($this->userWithRole('editor'));

        $created = $this->postJson('/api/v1/admin/posts', [
            'title' => 'Original title',
            'status' => 'draft',
            'content' => '<p>Original body.</p>',
        ])->assertCreated();

        $postId = $created->json('data.id');
        $firstRevision = PostRevision::query()->where('post_id', $postId)->first();

        $this->putJson("/api/v1/admin/posts/{$postId}", [
            'title' => 'Second version',
            'status' => 'draft',
            'content' => '<p>Second body.</p>',
        ])->assertOk();

        $this->putJson("/api/v1/admin/posts/{$postId}", [
            'title' => 'Third version',
            'status' => 'draft',
            'content' => '<p>Third body.</p>',
        ])->assertOk();

        $this->postJson("/api/v1/admin/posts/{$postId}/revisions/{$firstRevision->id}/restore")->assertOk();

        // Four revisions: create, two edits, and the pre-restore snapshot.
        $this->assertCount(4, PostRevision::query()->where('post_id', $postId)->get());

        $preRestore = PostRevision::query()->where('post_id', $postId)->latest('id')->first();
        $this->assertSame('Third version', $preRestore->title);
        $this->assertSame('<p>Third body.</p>', $preRestore->content);

        $this->postJson("/api/v1/admin/posts/{$postId}/revisions/{$preRestore->id}/restore")->assertOk();

        $post = Post::find($postId);
        $this->assertSame('Third version', $post->title);
        $this->assertSame('<p>Third body.</p>', (string) $post->content);
    }

    public function test_restoring_an_html_revision_reverts_to_classic_format(): void
    {
        Sanctum::actingAs($this->userWithRole('editor'));

        $created = $this->postJson('/api/v1/admin/posts', [
            'title' => 'Classic post',
            'status' => 'draft',
            'content' => '<p>Old body <script>alert(2)</script></p>',
        ])->assertCreated();

        $postId = $created->json('data.id');
        $firstRevision = PostRevision::query()->where('post_id', $postId)->first();

        $this->putJson("/api/v1/admin/posts/{$postId}", [
            'title' => 'Classic post, edited',
            'status' => 'draft',
            'blocks' => $this->sampleBlocks(),
        ])->assertOk();

        $response = $this->postJson(
            "/api/v1/admin/posts/{$postId}/revisions/{$firstRevision->id}/restore",
        )->assertOk();

        $this->assertNull($response->json('data.blocks'));
        $this->assertSame('html', $response->json('data.content_format'));
    }

    public function test_editor_may_restore_others_posts(): void
    {
        $author = $this->userWithRole('author');
        Sanctum::actingAs($this->userWithRole('editor'));

        $post = Post::factory()->for($author, 'author')->create([
            'title' => 'Author title',
            'status' => 'draft',
        ]);
        PostRevision::capture($post, $author->getKey());
        $post->update(['title' => 'Changed title']);
        $revision = PostRevision::query()->where('post_id', $post->getKey())->first();

        $this->postJson("/api/v1/admin/posts/{$post->getKey()}/revisions/{$revision->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.title', 'Author title');
    }

    public function test_author_may_not_restore_someone_elses_post(): void
    {
        $editor = $this->userWithRole('editor');
        Sanctum::actingAs($this->userWithRole('author'));

        $post = Post::factory()->for($editor, 'author')->create([
            'title' => 'Editor title',
            'status' => 'draft',
        ]);
        PostRevision::capture($post, $editor->getKey());
        $post->update(['title' => 'Editor title v2']);
        $revision = PostRevision::query()->where('post_id', $post->getKey())->first();

        $this->postJson("/api/v1/admin/posts/{$post->getKey()}/revisions/{$revision->id}/restore")
            ->assertForbidden();

        $this->assertSame('Editor title v2', Post::find($post->getKey())->title);
    }

    public function test_revision_from_another_post_returns_404(): void
    {
        Sanctum::actingAs($this->userWithRole('editor'));

        $first = $this->postJson('/api/v1/admin/posts', [
            'title' => 'Post one',
            'status' => 'draft',
            'content' => '<p>One.</p>',
        ])->assertCreated();

        $second = $this->postJson('/api/v1/admin/posts', [
            'title' => 'Post two',
            'status' => 'draft',
            'content' => '<p>Two.</p>',
        ])->assertCreated();

        $firstRevision = PostRevision::query()
            ->where('post_id', $first->json('data.id'))
            ->first();

        $this->postJson(
            "/api/v1/admin/posts/{$second->json('data.id')}/revisions/{$firstRevision->id}/restore",
        )->assertNotFound();
    }
}
