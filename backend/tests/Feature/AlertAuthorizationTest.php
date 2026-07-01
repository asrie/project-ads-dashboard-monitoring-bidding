<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AlertCategory;
use App\Enums\AlertStatus;
use App\Enums\Role;
use App\Enums\Severity;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAlert(): Alert
    {
        return Alert::create([
            'severity' => Severity::High->value,
            'category' => AlertCategory::Bidding->value,
            'metric' => 'timeout_rate',
            'current_value' => 27.5,
            'threshold_value' => 25,
            'message' => 'Bidder timeout rate exceeded critical threshold.',
            'suggested_action' => 'Review bidder configuration.',
            'status' => AlertStatus::Open->value,
            'triggered_at' => now(),
        ]);
    }

    private function tokenFor(Role $role): string
    {
        $user = User::factory()->role($role)->create();

        return auth('api')->login($user);
    }

    public function test_protected_endpoint_rejects_unauthenticated(): void
    {
        $this->patchJson('/api/v1/alerts/'.$this->makeAlert()->id.'/acknowledge')
            ->assertStatus(401);
    }

    public function test_viewer_cannot_acknowledge_alert(): void
    {
        $alert = $this->makeAlert();

        $this->withToken($this->tokenFor(Role::Viewer))
            ->patchJson("/api/v1/alerts/{$alert->id}/acknowledge")
            ->assertStatus(403)
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');
    }

    public function test_adops_can_acknowledge_alert(): void
    {
        $alert = $this->makeAlert();

        $this->withToken($this->tokenFor(Role::AdOps))
            ->patchJson("/api/v1/alerts/{$alert->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath('data.status', AlertStatus::Acknowledged->value);

        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'status' => AlertStatus::Acknowledged->value,
        ]);
    }
}
