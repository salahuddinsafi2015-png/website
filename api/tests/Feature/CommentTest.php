<?php

namespace Tests\Feature;

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_comments_are_held_for_moderation(): void
    {
        $post = Post::factory()->published()->create(['slug' => 'post']);

        $this->postJson('/api/v1/posts/post/comments', [
            'author_name' => 'Visitor',
            'author_email' => 'visitor@example.com',
            'content' => 'Interesting article.',
        ])->assertCreated();

        $this->assertDatabaseHas('comments', [
            'post_id' => $post->getKey(),
            'status' => CommentStatus::Pending->value,
        ]);

        // Pending comments are not visible on the public endpoint.
        $this->getJson('/api/v1/posts/post/comments')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_approved_comments_are_listed(): void
    {
        $post = Post::factory()->published()->create(['slug' => 'post']);
        Comment::factory()->create(['post_id' => $post->getKey()]);

        $this->getJson('/api/v1/posts/post/comments')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_comments_are_closed_when_the_post_disables_them(): void
    {
        Post::factory()->published()->create(['slug' => 'closed', 'comment_status' => 'closed']);

        $this->postJson('/api/v1/posts/closed/comments', [
            'author_name' => 'Visitor',
            'author_email' => 'visitor@example.com',
            'content' => 'Hello?',
        ])->assertForbidden();
    }

    public function test_replies_must_belong_to_the_same_post(): void
    {
        $post = Post::factory()->published()->create(['slug' => 'post']);
        $other = Post::factory()->published()->create(['slug' => 'other']);
        $parent = Comment::factory()->create(['post_id' => $other->getKey()]);

        $this->postJson('/api/v1/posts/post/comments', [
            'author_name' => 'Visitor',
            'author_email' => 'visitor@example.com',
            'content' => 'Cross-post reply',
            'parent_id' => $parent->getKey(),
        ])->assertStatus(422);
    }

    public function test_thread_depth_is_capped(): void
    {
        $post = Post::factory()->published()->create(['slug' => 'post']);

        $parent = Comment::factory()->create(['post_id' => $post->getKey()]);
        $child = Comment::factory()->create(['post_id' => $post->getKey(), 'parent_id' => $parent->getKey()]);
        $grandchild = Comment::factory()->create(['post_id' => $post->getKey(), 'parent_id' => $child->getKey()]);

        $this->postJson('/api/v1/posts/post/comments', [
            'author_name' => 'Visitor',
            'author_email' => 'visitor@example.com',
            'content' => 'Too deep',
            'parent_id' => $grandchild->getKey(),
        ])->assertStatus(422);
    }

    public function test_moderators_can_approve_comments(): void
    {
        $editor = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $editor->assignRole('editor');

        $comment = Comment::factory()->pending()->create();

        Sanctum::actingAs($editor);

        $this->patchJson("/api/v1/admin/comments/{$comment->getKey()}", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_authors_cannot_moderate_comments(): void
    {
        $author = User::factory()->create();
        $this->seed(RolePermissionSeeder::class);
        $author->assignRole('author');

        $comment = Comment::factory()->pending()->create();

        Sanctum::actingAs($author);

        $this->patchJson("/api/v1/admin/comments/{$comment->getKey()}", ['status' => 'approved'])
            ->assertForbidden();
    }
}
