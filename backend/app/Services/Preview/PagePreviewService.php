<?php

declare(strict_types=1);

namespace App\Services\Preview;

use App\Models\AdSlot;
use App\Models\Domain;
use App\Models\Page;
use App\Models\PagePreview;
use App\Models\SlotPerformanceDaily;
use App\Support\Metrics;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Drives the Playwright renderer to capture a mobile screenshot + ad-slot
 * geometry, then enriches detected slots with local data (ad_slots match,
 * aggregate performance, header-bidding flag) and persists the capture.
 */
class PagePreviewService
{
    public function configured(): bool
    {
        return ! empty(config('services.renderer.url'));
    }

    public function capture(Domain $domain, ?Page $page, string $device, ?string $userId): PagePreview
    {
        if (! $this->configured()) {
            throw new RuntimeException('Renderer is not configured (RENDERER_URL missing).');
        }

        $base = rtrim($domain->url, '/');
        $targetUrl = $page ? $base.'/'.ltrim($page->path, '/') : $base;

        $response = Http::timeout((int) config('services.renderer.timeout', 90))
            ->acceptJson()
            ->post(rtrim((string) config('services.renderer.url'), '/').'/render', [
                'url' => $targetUrl,
                'device' => $device,
            ]);

        if (! $response->successful()) {
            return $this->persistFailure($domain, $page, $device, $targetUrl, $userId, $response->json('error') ?? 'Renderer error '.$response->status());
        }

        $data = $response->json();
        $imagePath = $this->storeScreenshot($data['screenshot_base64'] ?? null);

        $prebidUnits = array_map('strval', $data['prebid_units'] ?? []);
        $slots = $this->enrichSlots($domain, $data['slots'] ?? [], $prebidUnits);

        return PagePreview::create([
            'domain_id' => $domain->id,
            'page_id' => $page?->id,
            'device' => $device,
            'url' => $targetUrl,
            'status' => 'completed',
            'image_path' => $imagePath,
            'page_width' => (int) ($data['page_width'] ?? 0),
            'page_height' => (int) ($data['page_height'] ?? 0),
            'viewport_css_width' => (int) ($data['viewport_css_width'] ?? 390),
            'slot_count' => count($slots),
            'slots' => $slots,
            'header' => $data['header'] ?? null,
            'requested_by' => $userId,
            'captured_at' => now(),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawSlots
     * @param  string[]  $prebidUnits
     * @return array<int, array<string, mixed>>
     */
    private function enrichSlots(Domain $domain, array $rawSlots, array $prebidUnits): array
    {
        $out = [];
        foreach ($rawSlots as $s) {
            $path = $s['ad_unit_path'] ?? null;
            $elementId = $s['element_id'] ?? null;

            $isHeaderBidding = $this->inPrebid($prebidUnits, $path, $elementId);
            $slot = $path ? $this->matchSlot($domain, $path) : null;

            $out[] = [
                'element_id' => $elementId,
                'ad_unit_path' => $path,
                'rect' => $s['rect'] ?? null,
                'sizes' => $s['sizes'] ?? [],
                'type' => $isHeaderBidding ? 'header_bidding' : 'direct',
                'matched' => (bool) $slot,
                'slot_name' => $slot?->name,
                'metrics' => $slot ? $this->slotMetrics($slot->id) : null,
            ];
        }

        return $out;
    }

    /** @param string[] $prebidUnits */
    private function inPrebid(array $prebidUnits, ?string $path, ?string $elementId): bool
    {
        foreach ($prebidUnits as $code) {
            if ($code !== '' && ($code === $path || $code === $elementId)) {
                return true;
            }
        }

        return false;
    }

    private function matchSlot(Domain $domain, string $path): ?AdSlot
    {
        return AdSlot::query()
            ->where('domain_id', $domain->id)
            ->where(fn ($q) => $q->where('ad_unit_path', $path)->orWhere('name', $path))
            ->first();
    }

    private function slotMetrics(string $slotId): ?array
    {
        $row = SlotPerformanceDaily::query()
            ->where('slot_id', $slotId)
            ->selectRaw('COALESCE(SUM(revenue),0) rev, COALESCE(SUM(impressions),0) impr, COALESCE(SUM(ad_requests),0) req, COALESCE(AVG(viewability),0) vw')
            ->first();

        if (! $row || (int) $row->req === 0) {
            return null;
        }

        $impr = (int) $row->impr;
        $req = (int) $row->req;
        $rev = (float) $row->rev;

        return [
            'revenue' => round($rev, 2),
            'impressions' => $impr,
            'fill_rate' => Metrics::ratio($impr, $req),
            'ecpm' => Metrics::ecpm($rev, $impr),
            'viewability' => round((float) $row->vw, 1),
        ];
    }

    private function storeScreenshot(?string $base64): ?string
    {
        if (! $base64) {
            return null;
        }
        $binary = base64_decode($base64, true);
        if ($binary === false) {
            return null;
        }
        $path = 'previews/'.Str::uuid()->toString().'.jpg';
        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    private function persistFailure(Domain $domain, ?Page $page, string $device, string $url, ?string $userId, string $error): PagePreview
    {
        return PagePreview::create([
            'domain_id' => $domain->id,
            'page_id' => $page?->id,
            'device' => $device,
            'url' => $url,
            'status' => 'failed',
            'error' => Str::limit($error, 500),
            'requested_by' => $userId,
            'captured_at' => now(),
        ]);
    }

    /** Read the stored screenshot back as a data URI for the API detail response. */
    public function imageDataUri(PagePreview $preview): ?string
    {
        if (! $preview->image_path || ! Storage::disk('local')->exists($preview->image_path)) {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode(Storage::disk('local')->get($preview->image_path));
    }
}
