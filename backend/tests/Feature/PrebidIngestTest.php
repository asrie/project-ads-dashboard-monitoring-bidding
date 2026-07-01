<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Domain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrebidIngestTest extends TestCase
{
    use RefreshDatabase;

    private function withKey(): void
    {
        config(['services.prebid.ingest_key' => 'secret-key']);
    }

    private function domain(): Domain
    {
        return Domain::create(['name' => 'Kompas TV', 'url' => 'https://www.kompas.tv', 'is_active' => true]);
    }

    private function payload(string $auctionId = 'auc_123'): array
    {
        return [
            'domain' => 'https://www.kompas.tv',
            'auctions' => [[
                'auction_id' => $auctionId,
                'page_path' => '/news',
                'device' => 'mobile',
                'started_at' => '2026-06-20T10:00:00Z',
                'duration_ms' => 820,
                'bidder_count' => 8,
                'bids_received' => 5,
                'timeouts' => 1,
                'won_bidder' => 'rubicon',
                'cpm' => 1.23,
                'status' => 'timeout',
            ]],
        ];
    }

    public function test_ingestion_disabled_when_no_key_configured(): void
    {
        config(['services.prebid.ingest_key' => null]);

        $this->postJson('/api/v1/ingest/prebid', $this->payload())
            ->assertStatus(503)
            ->assertJsonPath('errors.0.code', 'INGEST_DISABLED');
    }

    public function test_rejects_missing_or_wrong_key(): void
    {
        $this->withKey();
        $this->domain();

        $this->postJson('/api/v1/ingest/prebid', $this->payload())
            ->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'INVALID_INGEST_KEY');

        $this->withHeaders(['X-Ingest-Key' => 'nope'])
            ->postJson('/api/v1/ingest/prebid', $this->payload())
            ->assertStatus(401);
    }

    public function test_unknown_domain_returns_422(): void
    {
        $this->withKey();
        // no domain created

        $this->withHeaders(['X-Ingest-Key' => 'secret-key'])
            ->postJson('/api/v1/ingest/prebid', $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'UNKNOWN_DOMAIN');
    }

    public function test_valid_ingest_stores_auction_and_is_idempotent(): void
    {
        $this->withKey();
        $domain = $this->domain();

        $this->withHeaders(['X-Ingest-Key' => 'secret-key'])
            ->postJson('/api/v1/ingest/prebid', $this->payload())
            ->assertStatus(202)
            ->assertJsonPath('data.stored', 1);

        $this->assertDatabaseHas('prebid_auctions', [
            'auction_id' => 'auc_123',
            'domain_id' => $domain->id,
            'device' => 'mobile',
            'status' => 'timeout',
            'won_bidder' => 'rubicon',
        ]);

        // Re-send same auction_id -> updated, not duplicated.
        $this->withHeaders(['X-Ingest-Key' => 'secret-key'])
            ->postJson('/api/v1/ingest/prebid', $this->payload())
            ->assertStatus(202);

        $this->assertDatabaseCount('prebid_auctions', 1);
    }

    public function test_key_via_body_works_for_beacon(): void
    {
        $this->withKey();
        $this->domain();

        // navigator.sendBeacon path: key in body, no header.
        $body = $this->payload();
        $body['ingest_key'] = 'secret-key';

        $this->postJson('/api/v1/ingest/prebid', $body)
            ->assertStatus(202);
    }

    public function test_validation_rejects_empty_auctions(): void
    {
        $this->withKey();
        $this->domain();

        $this->withHeaders(['X-Ingest-Key' => 'secret-key'])
            ->postJson('/api/v1/ingest/prebid', ['domain' => 'https://www.kompas.tv', 'auctions' => []])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }
}
