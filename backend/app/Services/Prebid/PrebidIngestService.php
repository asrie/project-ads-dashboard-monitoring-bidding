<?php

declare(strict_types=1);

namespace App\Services\Prebid;

use App\Models\Domain;
use App\Models\Page;
use App\Models\PrebidAuction;
use Carbon\CarbonImmutable;

class PrebidIngestService
{
    /**
     * Ingest a batch of Prebid auction events for one domain.
     *
     * @param  array{domain: string, auctions: array<int, array<string, mixed>>}  $payload
     * @return array{resolved: bool, domain?: string, received: int, stored: int}
     */
    public function ingest(array $payload): array
    {
        $domain = $this->resolveDomain((string) $payload['domain']);
        $auctions = $payload['auctions'] ?? [];

        if (! $domain) {
            return ['resolved' => false, 'received' => count($auctions), 'stored' => 0];
        }

        // Pre-map existing pages for this domain (path => id) to avoid N+1.
        $pages = Page::where('domain_id', $domain->id)->pluck('id', 'path');

        $stored = 0;
        foreach ($auctions as $a) {
            $startedAt = isset($a['started_at'])
                ? CarbonImmutable::parse($a['started_at'])
                : CarbonImmutable::now();

            PrebidAuction::updateOrCreate(
                ['auction_id' => $a['auction_id']],
                [
                    'domain_id' => $domain->id,
                    'page_id' => isset($a['page_path']) ? ($pages[$a['page_path']] ?? null) : null,
                    'device' => $a['device'] ?? 'desktop',
                    'started_at' => $startedAt,
                    'duration_ms' => (int) ($a['duration_ms'] ?? 0),
                    'bidder_count' => (int) ($a['bidder_count'] ?? 0),
                    'bids_received' => (int) ($a['bids_received'] ?? 0),
                    'timeouts' => (int) ($a['timeouts'] ?? 0),
                    'errors' => (int) ($a['errors'] ?? 0),
                    'won_bidder' => $a['won_bidder'] ?? null,
                    'cpm' => round((float) ($a['cpm'] ?? 0), 4),
                    'status' => $a['status'] ?? 'completed',
                ],
            );
            $stored++;
        }

        return ['resolved' => true, 'domain' => $domain->name, 'received' => count($auctions), 'stored' => $stored];
    }

    /** Match a publisher URL/host to a registered Domain. */
    private function resolveDomain(string $value): ?Domain
    {
        $host = parse_url($value, PHP_URL_HOST) ?: $value;
        $host = preg_replace('/^www\./i', '', strtolower(trim($host)));

        if ($host === '') {
            return null;
        }

        return Domain::all()->first(function (Domain $d) use ($host) {
            $dHost = strtolower((string) (parse_url($d->url, PHP_URL_HOST) ?: $d->url));
            $dHost = preg_replace('/^www\./i', '', $dHost);

            return $dHost === $host;
        });
    }
}
