<?php

declare(strict_types=1);

namespace App\Services\Prebid;

use App\Models\PrebidAuction;
use App\Support\DashboardFilters;
use App\Support\Metrics;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PrebidService
{
    public function health(DashboardFilters $filters): array
    {
        $base = $this->baseQuery($filters);

        $row = (clone $base)->selectRaw('
            COUNT(*) as total,
            COALESCE(AVG(duration_ms),0) as avg_duration,
            COALESCE(MAX(duration_ms),0) as max_duration,
            COALESCE(SUM(timeouts),0) as timeouts,
            COALESCE(SUM(errors),0) as errors,
            COALESCE(SUM(bids_received),0) as bids_received,
            COALESCE(AVG(bidder_count),0) as avg_bidders
        ')->first();

        // p95 via PostgreSQL percentile_cont
        $p95 = (float) ((clone $base)
            ->selectRaw('COALESCE(percentile_cont(0.95) WITHIN GROUP (ORDER BY duration_ms),0) as p95')
            ->value('p95') ?? 0);

        $total = (int) $row->total;
        $avgDuration = round((float) $row->avg_duration, 1);

        $distribution = (clone $base)
            ->selectRaw("date_trunc('day', started_at) as day, AVG(duration_ms) as avg_duration, COUNT(*) as auctions, SUM(timeouts) as timeouts")
            ->groupByRaw("date_trunc('day', started_at)")
            ->orderByRaw("date_trunc('day', started_at)")
            ->get()
            ->map(fn ($r) => [
                'date' => substr((string) $r->day, 0, 10),
                'avg_duration_ms' => round((float) $r->avg_duration, 1),
                'auctions' => (int) $r->auctions,
                'timeouts' => (int) $r->timeouts,
            ])->all();

        return [
            'total_auctions' => $total,
            'avg_duration_ms' => $avgDuration,
            'p95_duration_ms' => round($p95, 1),
            'max_duration_ms' => (int) $row->max_duration,
            'timeout_count' => (int) $row->timeouts,
            'timeout_rate' => Metrics::ratio((int) $row->timeouts, $total),
            'error_count' => (int) $row->errors,
            'error_rate' => Metrics::ratio((int) $row->errors, $total),
            'avg_bidders' => round((float) $row->avg_bidders, 1),
            'status' => match (true) {
                $avgDuration >= 2000 => 'critical',
                $avgDuration >= 1000 => 'warning',
                default => 'healthy',
            },
            'timeseries' => $distribution,
        ];
    }

    public function auctions(DashboardFilters $filters, ?string $status): LengthAwarePaginator
    {
        $query = $this->baseQuery($filters)
            ->with('domain')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('started_at');

        $paginator = $query->paginate(perPage: $filters->perPage, page: $filters->page);

        $paginator->getCollection()->transform(fn (PrebidAuction $a) => [
            'id' => $a->id,
            'auction_id' => $a->auction_id,
            'domain' => $a->domain?->name,
            'device' => $a->device,
            'started_at' => $a->started_at?->toIso8601String(),
            'duration_ms' => $a->duration_ms,
            'bidder_count' => $a->bidder_count,
            'bids_received' => $a->bids_received,
            'timeouts' => $a->timeouts,
            'errors' => $a->errors,
            'won_bidder' => $a->won_bidder,
            'cpm' => round((float) $a->cpm, 2),
            'status' => $a->status,
        ]);

        return $paginator;
    }

    private function baseQuery(DashboardFilters $filters)
    {
        return PrebidAuction::query()
            ->whereBetween('started_at', [$filters->dateFrom, $filters->dateTo])
            ->when($filters->domainId, fn ($q) => $q->where('domain_id', $filters->domainId))
            ->when($filters->pageId, fn ($q) => $q->where('page_id', $filters->pageId))
            ->when($filters->device, fn ($q) => $q->where('device', $filters->device));
    }
}
