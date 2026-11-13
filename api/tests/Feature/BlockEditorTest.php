<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlockEditorTest extends TestCase
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
            ['type' => 'heading', 'level' => 2, 'text' => 'Welcome <em>reader</em>'],
            ['type' => 'paragraph', 'text' => '<script>alert(1)</script> A normal sentence.'],
            ['type' => 'quote', 'text' => 'Stay humble.', 'citation' => 'The Editor'],
            ['type' => 'list', 'style' => 'numbered', 'items' => ['First', 'Second']],
            ['type' => 'image', 'src' => '/media/cache/wide.jpg', 'alt' => 'A sunset', 'caption' => 'Night falls'],
            ['type' => 'code', 'language' => 'php', 'code' => '<?php echo "hi"; ?>'],
            ['type' => 'divider'],
        ];
    }

    public function test_blocks_render_into_sanitised_html(): void
    {
        Sanctum::actingAs($this->userWithRole('editor'));

        $response = $this->postJson('/api/v1/admin/posts', [
            'title' => 'Structured article',
            'status' => 'draft',
            'blocks' => $this->sampleBlocks(),
        ])->assertCreated();

        $content = (string) $response->json('data.content');

        $this->assertSame('blocks', $response->json('data.content_format'));

        // Labels are escaped as text, never kept as markup on plain blocks.
        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString('<script', $content);
        $this->assertStringContainsString('&lt;em&gt;reader&lt;/em&gt;', $content);

        // Each block type renders its semantic element.
        $this->assertStringContainsString('<h2>Welcome', $content);
        $this->assertStringContainsString('<blockquote><p>Stay humble.</p><cite>The Editor</cite></blockquote>', $content);
        $this->assertStringContainsString('<ol>', $content);
        $this->assertStringContainsString('<li>Second</li>', $content);
        $this->assertStringContainsString('<img src="/media/cache/wide.jpg" alt="A sunset"', $content);
        $this->assertStringContainsString('<figcaption>Night falls</figcaption>', $content);
        $this->assertStringContainsString('<pre><code class="language-php">', $content);
        $this->assertStringContainsString('<hr>', $content);

        $this->assertDatabaseHas('posts', [
            'title' => 'Structured article',
            'content_format' => 'blocks',
        ]);

        $first = Post::query()->where('title', 'Structured article')->first();

        $this->assertSame('heading', $first->blocks[0]['type']);
        $this->assertSame(2, $first->blocks[0]['level']);
    }

    public function test_image_src_must_be_a_safe_url(): void
    {
        Sanctum::actingAs($this->userWithRole('editor'));

        $blocks = $this->sampleBlocks();

        $this->postJson('/api/v1/admin/posts', [
            'title' => 'Bad image',
            'status' => 'draft',
            'blocks' => [
                ['type' => 'image', 'src' => 'javascript:alert(1)'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('blocks.0.src');

        $this->postJson('/api/v1/admin/posts', [
            'title' => 'Data url image',
            'status' => 'draft',
            'blocks' => [
                ['type' => 'image', 'src' => 'data:text/html,<script>alert(1)</script>'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('blocks.0.src');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_unknown_block_types_and_bad_fields_are_rejected(): void
    {
        Sanctum::actingAs($this->userWithRole('editor'));

        $this->postJson('/api/v1/admin/posts', [
            'title' => 'Mystery block',
            'status' => 'draft',
            'blocks' => [['type' => 'spoiler', 'text' => 'Tanaka']],
        ])->assertStatus(422)->assertJsonValidationErrors('blocks.0.type');

        $this->postJson('/api/v1/admin/posts', [
            'title' => 'Bad level',
            'status' => 'draft',
            'blocks' => [['type' => 'heading', 'level' => 9, 'text' => 'Too deep']],
        ])->assertStatus(422)->assertJsonValidationErrors('blocks.0.level');

        $this->postJson('/api/v1/admin/posts', [
            'title' => 'No type',
            'status' => 'draft',
            'blocks' => [['text' => 'Orphan']],
        ])->assertStatus(422)->assertJsonValidationErrors('blocks.0.type');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_switching_back_to_html_clears_the_block_tree(): void
    {
        Sanctum::actingAs($this->userWithRole('editor'));

        $blocks = $this->sampleBlocks();

        $post = $this->postJson('/api/v1/admin/posts', [
            'title' => 'Switchable',
            'status' => 'draft',
            'blocks' => $blocks,
        ])->assertCreated()->json('data');

        $this->patchJson("/api/v1/admin/posts/{$post['id']}", [
            'content' => '<p>Classic editing takes over.</p>',
            'blocks' => [],
        ])->assertOk()->assertJsonPath('data.content_format', 'html');

        $post = Post::find($post['id']);

        $this->assertStringContainsString('Classic editing takes over.', $post->content);
        $this->assertNull($post->blocks);
    }

    public function test_revisions_snapshot_the_block_tree(): void
    {
        Sanctum::actingAs($this->userWithRole('editor'));

        $post = $this->postJson('/api/v1/admin/posts', [
            'title' => 'Versioned',
            'status' => 'draft',
            'blocks' => $this->sampleBlocks(),
        ])->assertCreated()->json('data');

        $edited = $this->sampleBlocks();
        $edited[0]['text'] = 'A new heading on top';

        $this->patchJson("/api/v1/admin/posts/{$post['id']}", [
            'blocks' => $edited,
        ])->assertOk()->assertJsonPath('data.content_format', 'blocks');

        $this->assertSame(2, PostRevision::query()->where('post_id', $post['id'])->count());

        $snapshot = PostRevision::query()
            ->where('post_id', $post['id'])
            ->orderBy('created_at')
            ->get();

        // Revisions capture the pre-edit state, so both snapshots hold the
        // original heading; the post itself has moved on.
        $this->assertSame('Welcome <em>reader</em>', $snapshot[1]->blocks[0]['text']);
        $this->assertSame('Welcome <em>reader</em>', $snapshot[0]->blocks[0]['text']);

        $latest = Post::find($post['id']);

        $this->assertSame('A new heading on top', $latest->blocks[0]['text']);
        $this->assertStringContainsString('<h2>A new heading on top</h2>', $latest->content);
    }

    public function test_html_only_posts_keep_working_without_blocks(): void
    {
        Sanctum::actingAs($this->userWithRole('editor'));

        $post = $this->postJson('/api/v1/admin/posts', [
            'title' => 'Classic mode',
            'status' => 'draft',
            'content' => '<p>Just HTML today.</p>',
        ])->assertCreated()->assertJsonPath('data.content_format', 'html');

        $post = $this->postJson('/api/v1/admin/posts', [
            'title' => 'Classic two',
            'status' => 'draft',
            'content' => '<p>More.</p>',
        ])->assertCreated();

        $this->assertNull($post->json('data.blocks'));

        $post = Post::find($post->json('data.id'));

        $this->assertSame('html', $post->content_format);
        $this->assertNull($post->blocks);
    }
}
