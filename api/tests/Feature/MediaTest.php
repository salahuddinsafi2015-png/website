<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_guests_cannot_upload_media(): void
    {
        $this->postJson('/api/v1/admin/media', [
            'file' => UploadedFile::fake()->create('report.pdf', 20, 'application/pdf'),
        ])->assertUnauthorized();
    }

    public function test_authors_can_upload_an_allowed_file(): void
    {
        $author = User::factory()->create();
        $author->assignRole('author');
        Sanctum::actingAs($author);

        $response = $this->postJson('/api/v1/admin/media', [
            'file' => UploadedFile::fake()->create('report.pdf', 20, 'application/pdf'),
            'alt_text' => 'Quarterly report',
        ])->assertCreated();

        $media = Media::first();

        $this->assertNotNull($media);
        Storage::disk('public')->assertExists($media->path);
        $this->assertSame('application/pdf', $response->json('data.mime_type'));
    }

    public function test_disallowed_file_types_are_rejected(): void
    {
        $author = User::factory()->create();
        $author->assignRole('author');
        Sanctum::actingAs($author);

        $this->postJson('/api/v1/admin/media', [
            'file' => UploadedFile::fake()->create('shell.php', 5, 'text/x-php'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_owners_can_delete_their_media(): void
    {
        $author = User::factory()->create();
        $author->assignRole('author');
        Sanctum::actingAs($author);

        $media = Media::factory()->create(['user_id' => $author->getKey()]);
        Storage::disk('public')->put($media->path, 'contents');

        $this->deleteJson("/api/v1/admin/media/{$media->getKey()}")->assertOk();

        $this->assertDatabaseMissing('media', ['id' => $media->getKey()]);
        Storage::disk('public')->assertMissing($media->path);
    }

    public function test_authors_cannot_delete_someone_elses_media(): void
    {
        $author = User::factory()->create();
        $author->assignRole('author');
        Sanctum::actingAs($author);

        $media = Media::factory()->create(['user_id' => User::factory()->create()->getKey()]);

        $this->deleteJson("/api/v1/admin/media/{$media->getKey()}")->assertForbidden();
    }
}
