<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_and_user(): void
    {
        User::factory()->role(Role::ProgrammaticRevenue)->create([
            'email' => 'rev@kgmedia.io',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'rev@kgmedia.io',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => ['access_token', 'token_type', 'expires_in', 'user' => ['id', 'email', 'role']],
                'meta' => ['request_id', 'timestamp'],
                'errors',
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'rev@kgmedia.io']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'rev@kgmedia.io',
            'password' => 'wrong-password',
        ])->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'UNAUTHENTICATED');
    }

    public function test_protected_route_returns_401_without_json_accept_header(): void
    {
        // API-only backend must not redirect to a login route (would 500).
        $this->get('/api/v1/dashboard/overview', ['Accept' => 'text/html'])
            ->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'UNAUTHENTICATED');
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_logout_invalidates_token(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        // Token should no longer be accepted after logout (blacklist).
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    }
}
