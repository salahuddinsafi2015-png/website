<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_administrators_can_create_and_list_backups(): void
    {
        Sanctum::actingAs($this->userWithRole('administrator'));

        $created = $this->postJson('/api/v1/admin/backups', ['kind' => 'database'])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'database');

        $backup = Backup::query()->findOrFail($created->json('data.id'));

        Storage::disk('local')->assertExists($backup->path);

        $this->assertSame(64, strlen($backup->checksum));
        $this->assertGreaterThan(0, $backup->size);

        $this->getJson('/api/v1/admin/backups')
            ->assertOk()
            ->assertJsonPath('data.0.id', $backup->id)
            ->assertJsonStructure(['meta' => ['total_size', 'free_space', 'kinds']]);
    }

    public function test_restoring_a_database_backup_brings_rows_back_and_saves_a_safety_snapshot(): void
    {
        Sanctum::actingAs($this->userWithRole('administrator'));

        $post = Post::factory()->create(['title' => 'Before the restore']);

        $backupId = $this->postJson('/api/v1/admin/backups', ['kind' => 'database'])
            ->assertCreated()
            ->json('data.id');

        $post->update(['title' => 'Changed after the backup']);
        $extra = Post::factory()->create(['title' => 'Created after the backup']);

        $this->postJson("/api/v1/admin/backups/{$backupId}/restore")
            ->assertOk()
            ->assertJsonPath('meta.safety_backup.kind', 'database');

        $this->assertSame('Before the restore', $post->fresh()->title);
        $this->assertNull(Post::query()->find($extra->id));

        // The automatic snapshot preserves the state restore replaced.
        $this->assertSame(2, Backup::query()->count());
    }

    public function test_a_restore_refuses_a_tampered_snapshot(): void
    {
        Sanctum::actingAs($this->userWithRole('administrator'));

        $post = Post::factory()->create(['title' => 'Untouched']);
        $backupId = $this->postJson('/api/v1/admin/backups', ['kind' => 'database'])->json('data.id');
        $backup = Backup::query()->findOrFail($backupId);

        Storage::disk('local')->put($backup->path, '{"tampered":true}'."\n");

        $this->postJson("/api/v1/admin/backups/{$backupId}/restore")->assertStatus(500);

        $this->assertSame('Untouched', $post->fresh()->title);
        $this->assertSame(1, Backup::query()->count());
    }

    public function test_media_and_full_backups_restore_uploaded_files(): void
    {
        Sanctum::actingAs($this->userWithRole('administrator'));

        Storage::disk('public')->put('media/2026/11/photo.txt', 'original bytes');

        $mediaBackupId = $this->postJson('/api/v1/admin/backups', ['kind' => 'media'])
            ->assertCreated()
            ->json('data.id');

        Storage::disk('public')->delete('media/2026/11/photo.txt');

        $this->postJson("/api/v1/admin/backups/{$mediaBackupId}/restore")->assertOk();

        Storage::disk('public')->assertExists('media/2026/11/photo.txt');
        $this->assertSame('original bytes', Storage::disk('public')->get('media/2026/11/photo.txt'));

        // A full backup carries both the database and the media library.
        Post::factory()->create(['title' => 'In the full snapshot']);
        $fullBackupId = $this->postJson('/api/v1/admin/backups', ['kind' => 'full'])
            ->assertCreated()
            ->json('data.id');

        Post::query()->delete();
        Storage::disk('public')->delete('media/2026/11/photo.txt');

        $this->postJson("/api/v1/admin/backups/{$fullBackupId}/restore")->assertOk();

        $this->assertSame(1, Post::query()->count());
        Storage::disk('public')->assertExists('media/2026/11/photo.txt');
    }

    public function test_backups_can_be_downloaded_and_deleted(): void
    {
        Sanctum::actingAs($this->userWithRole('administrator'));

        $backupId = $this->postJson('/api/v1/admin/backups', ['kind' => 'database'])->json('data.id');
        $backup = Backup::query()->findOrFail($backupId);

        $this->get("/api/v1/admin/backups/{$backupId}/download")
            ->assertOk()
            ->assertDownload($backup->filename);

        $this->deleteJson("/api/v1/admin/backups/{$backupId}")->assertOk();

        Storage::disk('local')->assertMissing($backup->path);
        $this->assertNull(Backup::query()->find($backupId));
    }

    public function test_only_administrators_can_manage_backups(): void
    {
        $editor = $this->userWithRole('editor');

        Sanctum::actingAs($editor);
        $this->getJson('/api/v1/admin/backups')->assertForbidden();
        $this->postJson('/api/v1/admin/backups', ['kind' => 'database'])->assertForbidden();

        $this->actingAs($editor, 'web'); // reset guard state for clarity
        $this->getJson('/api/v1/admin/backups')->assertForbidden();
    }

    public function test_guests_cannot_touch_backups(): void
    {
        $this->getJson('/api/v1/admin/backups')->assertUnauthorized();
        $this->postJson('/api/v1/admin/backups', ['kind' => 'database'])->assertUnauthorized();
    }

    public function test_backup_kind_is_validated(): void
    {
        Sanctum::actingAs($this->userWithRole('administrator'));

        $this->postJson('/api/v1/admin/backups', ['kind' => 'everything'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kind');
    }
}
