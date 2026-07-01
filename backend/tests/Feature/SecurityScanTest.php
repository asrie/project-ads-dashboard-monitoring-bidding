<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityScanTest extends TestCase
{
    use RefreshDatabase;

    private function domain(): Domain
    {
        return Domain::create(['name' => 'Kompas TV', 'url' => 'https://www.kompas.tv', 'is_active' => true]);
    }

    private function tokenFor(Role $role): string
    {
        return auth('api')->login(User::factory()->role($role)->create());
    }

    public function test_scan_requires_authentication(): void
    {
        $this->postJson('/api/v1/security/scan', ['domain_id' => $this->domain()->id])
            ->assertStatus(401);
    }

    public function test_viewer_cannot_run_scan(): void
    {
        $this->withToken($this->tokenFor(Role::Viewer))
            ->postJson('/api/v1/security/scan', ['domain_id' => $this->domain()->id])
            ->assertStatus(403)
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');
    }

    public function test_scan_validates_domain_id(): void
    {
        $this->withToken($this->tokenFor(Role::Tech))
            ->postJson('/api/v1/security/scan', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }

    public function test_scan_history_requires_authentication(): void
    {
        $this->getJson('/api/v1/security/scans')->assertStatus(401);
    }

    public function test_domains_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/domains')->assertStatus(401);
    }
}
