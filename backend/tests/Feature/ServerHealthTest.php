<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_health_requires_authentication(): void
    {
        $this->getJson('/api/v1/server/health')
            ->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'UNAUTHENTICATED');
    }

    public function test_server_checks_requires_authentication(): void
    {
        $this->getJson('/api/v1/server/checks')
            ->assertStatus(401);
    }

    public function test_server_checks_validates_status_filter(): void
    {
        $token = auth('api')->login(User::factory()->create());

        $this->withToken($token)
            ->getJson('/api/v1/server/checks?status=sideways')
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }
}
