<?php

declare(strict_types=1);

namespace App\Services\Gam;

use App\Models\GamRequest;
use App\Support\DashboardFilters;
use App\Support\Metrics;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GamService
{
    public function health(DashboardFilters $filters): array
    {
        $base = $this->baseQuery($filters);

        $row = (clone $base)->selectRaw("
            COUNT(*) as total,
            COALESCE(SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END),0) as success,
            COALESCE(SUM(CASE WHEN status = 'empty' THEN 1 ELSE 0 END),0) as empty,
            COALESCE(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END),0) as failed,
            COALESCE(AVG(latency_ms),0) as avg_latency
        ")->first();

        $total = (int) $row->total;
        $failed = (int) $row->failed;
        $failureRate = Metrics::ratio($failed, $total);

        $timeseries = (clone $base)
            ->selectRaw("date_trunc('day', requested_at) as day,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                AVG(latency_ms) as avg_latency")
            ->groupByRaw("date_trunc('day', requested_at)")
            ->orderByRaw("date_trunc('day', requested_at)")
            ->get()
            ->map(fn ($r) => [
                'date' => substr((string) $r->day, 0, 10),
                'total_requests' => (int) $r->total,
                'failed_requests' => (int) $r->failed,
                'failure_rate' => Metrics::ratio((int) $r->failed, (int) $r->total),
                'avg_latency_ms' => round((float) $r->avg_latency, 1),
            ])->all();

        return [
            'total_requests' => $total,
            'success_requests' => (int) $row->success,
            'empty_requests' => (int) $row->empty,
            'failed_requests' => $failed,
            'success_rate' => Metrics::ratio((int) $row->success, $total),
            'failure_rate' => $failureRate,
            'avg_latency_ms' => round((float) $row->avg_latency, 1),
            'status' => match (true) {
                $failureRate > 15 => 'critical',
                $failureRate > 5 => 'warning',
                default => 'healthy',
            },
            'timeseries' => $timeseries,
        ];
    }

    public function requests(DashboardFilters $filters, ?string $status): LengthAwarePaginator
    {
        $query = $this->baseQuery($filters)
            ->with('domain')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('requested_at');

        $paginator = $query->paginate(perPage: $filters->perPage, page: $filters->page);

        $paginator->getCollection()->transform(fn (GamRequest $r) => [
            'id' => $r->id,
            'domain' => $r->domain?->name,
            'device' => $r->device,
            'requested_at' => $r->requested_at?->toIso8601String(),
            'ad_unit' => $r->ad_unit,
            'status' => $r->status,
            'latency_ms' => $r->latency_ms,
            'http_status' => $r->http_status,
            'line_item_id' => $r->line_item_id,
            'creative_id' => $r->creative_id,
        ]);

        return $paginator;
    }

    private function baseQuery(DashboardFilters $filters)
    {
        return GamRequest::query()
            ->whereBetween('requested_at', [$filters->dateFrom, $filters->dateTo])
            ->when($filters->domainId, fn ($q) => $q->where('domain_id', $filters->domainId))
            ->when($filters->pageId, fn ($q) => $q->where('page_id', $filters->pageId))
            ->when($filters->device, fn ($q) => $q->where('device', $filters->device));
    }
}
