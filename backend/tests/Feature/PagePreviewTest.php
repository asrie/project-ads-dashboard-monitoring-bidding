<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AdSlot;
use App\Models\Domain;
use App\Models\SlotPerformanceDaily;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PagePreviewTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(Role $role): string
    {
        return auth('api')->login(User::factory()->role($role)->create());
    }

    private function domainWithSlot(): Domain
    {
        $domain = Domain::create(['name' => 'Kompas TV', 'url' => 'https://www.kompas.tv', 'is_active' => true]);
        $slot = AdSlot::create([
            'domain_id' => $domain->id,
            'name' => 'Billboard Top',
            'ad_unit_path' => '/123/kompas_billboard',
            'device' => 'mobile',
            'is_active' => true,
        ]);
        SlotPerformanceDaily::create([
            'date' => '2026-06-10', 'domain_id' => $domain->id, 'slot_id' => $slot->id, 'device' => 'mobile',
            'ad_requests' => 1000, 'impressions' => 800, 'revenue' => 5.0, 'ecpm' => 6.25, 'fill_rate' => 80, 'viewability' => 70,
        ]);

        return $domain;
    }

    private function fakeRenderer(): void
    {
        config(['services.renderer.url' => 'http://renderer:3000']);
        Storage::fake('local');
        Http::fake([
            '*/render' => Http::response([
                'viewport_css_width' => 390,
                'page_width' => 390,
                'page_height' => 2400,
                'prebid_units' => ['/123/kompas_billboard'],
                'header' => ['x' => 0, 'y' => 0, 'w' => 390, 'h' => 60],
                'slots' => [
                    ['element_id' => 'div-gpt-1', 'ad_unit_path' => '/123/kompas_billboard', 'rect' => ['x' => 10, 'y' => 80, 'w' => 320, 'h' => 100], 'sizes' => [[320, 100]]],
                    ['element_id' => 'div-gpt-2', 'ad_unit_path' => '/123/unmapped_slot', 'rect' => ['x' => 10, 'y' => 600, 'w' => 300, 'h' => 250], 'sizes' => [[300, 250]]],
                ],
                'screenshot_base64' => base64_encode('FAKEJPEGBYTES'),
            ], 200),
        ]);
    }

    public function test_capture_requires_authentication(): void
    {
        $this->postJson('/api/v1/previews/capture', ['domain_id' => $this->domainWithSlot()->id])
            ->assertStatus(401);
    }

    public function test_viewer_cannot_capture(): void
    {
        config(['services.renderer.url' => 'http://renderer:3000']);

        $this->withToken($this->tokenFor(Role::Viewer))
            ->postJson('/api/v1/previews/capture', ['domain_id' => $this->domainWithSlot()->id])
            ->assertStatus(403);
    }

    public function test_capture_validates_domain(): void
    {
        $this->withToken($this->tokenFor(Role::Tech))
            ->postJson('/api/v1/previews/capture', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }

    public function test_capture_disabled_without_renderer(): void
    {
        config(['services.renderer.url' => null]);

        $this->withToken($this->tokenFor(Role::Tech))
            ->postJson('/api/v1/previews/capture', ['domain_id' => $this->domainWithSlot()->id])
            ->assertStatus(503)
            ->assertJsonPath('errors.0.code', 'RENDERER_DISABLED');
    }

    public function test_capture_renders_and_enriches_slots(): void
    {
        $this->fakeRenderer();
        $domain = $this->domainWithSlot();

        $res = $this->withToken($this->tokenFor(Role::ProgrammaticRevenue))
            ->postJson('/api/v1/previews/capture', ['domain_id' => $domain->id])
            ->assertStatus(201)
            ->assertJsonPath('data.slot_count', 2)
            ->assertJsonPath('data.slots.0.type', 'header_bidding')   // in prebid_units
            ->assertJsonPath('data.slots.0.matched', true)
            ->assertJsonPath('data.slots.0.slot_name', 'Billboard Top')
            ->assertJsonPath('data.slots.1.type', 'direct')
            ->assertJsonPath('data.slots.1.matched', false);

        // matched slot carries aggregate metrics; image returned as data URI
        $this->assertNotNull($res->json('data.slots.0.metrics.ecpm'));
        $this->assertStringStartsWith('data:image/jpeg;base64,', $res->json('data.image'));

        $this->assertDatabaseHas('page_previews', [
            'domain_id' => $domain->id,
            'status' => 'completed',
            'slot_count' => 2,
        ]);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/previews')->assertStatus(401);
    }
}
