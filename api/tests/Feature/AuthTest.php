<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_can_login_and_receive_a_token(): void
    {
        $user = User::factory()->create([
            'email' => 'editor@example.com',
            'password' => 'SecretPassword123',
        ]);
        $user->assignRole('editor');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'editor@example.com',
            'password' => 'SecretPassword123',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'roles']]);

        $this->assertNotNull($response->json('token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->getKey(),
            'tokenable_type' => User::class,
        ]);
        $this->assertNotNull($user->refresh()->last_login_at);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'editor@example.com',
            'password' => 'SecretPassword123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'editor@example.com',
            'password' => 'WrongPassword123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_suspended_users_cannot_authenticate(): void
    {
        User::factory()->create([
            'email' => 'blocked@example.com',
            'password' => 'SecretPassword123',
            'status' => UserStatus::Suspended,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'blocked@example.com',
            'password' => 'SecretPassword123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_authenticated_user_can_fetch_their_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('author');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->getKey())
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.roles.0', 'author');
    }

    public function test_guests_cannot_access_protected_endpoints(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->getJson('/api/v1/admin/posts')->assertUnauthorized();
    }

    public function test_user_can_logout_and_revoke_the_current_token(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_registration_is_disabled_by_default(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'New Reader',
            'email' => 'reader@example.com',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
        ])->assertForbidden();
    }
}
