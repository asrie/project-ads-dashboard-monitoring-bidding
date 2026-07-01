<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdSlot;
use App\Models\Domain;
use App\Models\SlotPerformanceDaily;
use App\Services\Gam\GamReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamIngestTest extends TestCase
{
    use RefreshDatabase;

    private function csv(): string
    {
        // Header mirrors GAM CSV_DUMP (Dimension.* / Column.*). Revenue in micros,
        // viewability as a 0..1 fraction.
        $header = 'Dimension.DATE,Dimension.AD_UNIT_NAME,Dimension.PARENT_AD_UNIT_NAME,'
            .'Dimension.DEVICE_CATEGORY_NAME,Column.TOTAL_AD_REQUESTS,Column.AD_SERVER_IMPRESSIONS,'
            .'Column.AD_SERVER_CPM_AND_CPC_REVENUE,Column.AD_SERVER_ACTIVE_VIEW_VIEWABLE_IMPRESSIONS_RATE';

        return implode("\n", [
            $header,
            // Existing slot (matches seeded "Billboard Top" under Kompas TV).
            '2026-06-10,Billboard Top,Kompas TV,Desktop,10000,8000,12000000,0.72',
            // New ad unit under a resolvable domain (Sonora) -> auto-created.
            '2026-06-10,Sonora Sticky,Sonora Network,Smartphone,5000,3000,4500000,0.55',
            // Unknown domain -> unresolved.
            '2026-06-10,Mystery Slot,Unknown Site,Desktop,1000,500,100000,0.40',
        ]);
    }

    public function test_ingest_matches_creates_and_resolves_domains(): void
    {
        $kompas = Domain::create(['name' => 'Kompas TV', 'url' => 'https://www.kompas.tv', 'is_active' => true]);
        $sonora = Domain::create(['name' => 'Sonora', 'url' => 'https://www.sonora.id', 'is_active' => true]);

        $billboard = AdSlot::create([
            'domain_id' => $kompas->id,
            'name' => 'Billboard Top',
            'ad_unit_path' => '/123/kompas_billboard',
            'device' => 'desktop',
            'is_active' => true,
        ]);

        $result = app(GamReportService::class)->ingestCsv($this->csv());

        // 3 rows: 1 matched existing, 1 auto-created, 1 unresolved domain.
        $this->assertSame(3, $result['rows']);
        $this->assertSame(2, $result['matched']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(2, $result['upserted']);
        $this->assertSame(1, $result['unresolved']);

        // Auto-created slot lives under the resolved Sonora domain.
        $this->assertDatabaseHas('ad_slots', [
            'domain_id' => $sonora->id,
            'name' => 'Sonora Sticky',
        ]);

        // Matched slot got real metrics incl. viewability (0.72 -> 72%).
        $perf = SlotPerformanceDaily::where('slot_id', $billboard->id)->first();
        $this->assertNotNull($perf);
        $this->assertSame(8000, (int) $perf->impressions);
        $this->assertEqualsWithDelta(12.0, (float) $perf->revenue, 0.001); // 12,000,000 micros
        $this->assertEqualsWithDelta(72.0, (float) $perf->viewability, 0.001);
    }

    public function test_ingest_without_autocreate_leaves_unmatched(): void
    {
        Domain::create(['name' => 'Sonora', 'url' => 'https://www.sonora.id', 'is_active' => true]);
        Domain::create(['name' => 'Kompas TV', 'url' => 'https://www.kompas.tv', 'is_active' => true]);

        $result = app(GamReportService::class)->ingestCsv($this->csv(), null, autoCreate: false);

        $this->assertSame(0, $result['created']);
        // Billboard Top has no slot (none seeded) -> unmatched; Sonora Sticky -> unmatched; Unknown -> unresolved.
        $this->assertSame(2, $result['unmatched']);
        $this->assertSame(1, $result['unresolved']);
        $this->assertSame(0, $result['upserted']);
    }
}
