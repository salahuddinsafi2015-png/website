<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Backup;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HealthAndCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_health_endpoint_reports_all_checks_without_configuration_details(): void
    {
        $response = $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', true)
            ->assertJsonPath('checks.storage', true)
            ->assertJsonPath('checks.cache', true);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertArrayNotHasKey('APP_KEY', $response->json());
    }

    public function test_health_endpoint_is_public_and_does_not_require_a_token(): void
    {
        // No Sanctum::actingAs: monitors call this anonymously.
        $this->get('/api/v1/health')->assertOk();
    }

    public function test_publish_scheduled_command_publishes_due_posts_only(): void
    {
        $due = Post::factory()->create([
            'status' => PostStatus::Future->value,
            'published_at' => now()->subMinute(),
        ]);

        $later = Post::factory()->scheduled()->create();

        $this->artisan('cms:publish-scheduled')->assertSuccessful();

        $this->assertSame(PostStatus::Published, $due->fresh()->status);
        $this->assertSame(PostStatus::Future, $later->fresh()->status);
    }

    public function test_backup_command_creates_a_snapshot_for_administrators(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $this->artisan('cms:backup', ['kind' => 'full'])
            ->assertSuccessful();

        $backup = Backup::query()->firstOrFail();

        $this->assertSame('full', $backup->kind);
        $this->assertSame($admin->id, $backup->user_id);
        Storage::disk('local')->assertExists($backup->path);
    }

    public function test_backup_command_rejects_an_unknown_kind(): void
    {
        User::factory()->create();

        $this->artisan('cms:backup', ['kind' => 'everything'])
            ->assertFailed();

        $this->assertSame(0, Backup::query()->count());
    }
}
