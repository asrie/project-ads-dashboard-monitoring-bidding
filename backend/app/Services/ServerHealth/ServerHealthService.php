<?php

declare(strict_types=1);

namespace App\Services\ServerHealth;

use App\Models\ServerCheck;
use App\Support\DashboardFilters;
use App\Support\Metrics;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ServerHealthService
{
    public function summary(DashboardFilters $filters): array
    {
        $base = $this->baseQuery($filters);

        $row = (clone $base)->selectRaw("
            COUNT(*) as total,
            COALESCE(SUM(CASE WHEN status = 'up' THEN 1 ELSE 0 END), 0) as up_count,
            COALESCE(SUM(CASE WHEN status = 'down' THEN 1 ELSE 0 END), 0) as down_count,
            COALESCE(AVG(response_time_ms) FILTER (WHERE status = 'up'), 0) as avg_resp,
            COALESCE(MAX(response_time_ms), 0) as max_resp
        ")->first();

        $p95 = (float) ((clone $base)
            ->where('status', 'up')
            ->selectRaw('COALESCE(percentile_cont(0.95) WITHIN GROUP (ORDER BY response_time_ms), 0) as p95')
            ->value('p95') ?? 0);

        $total = (int) $row->total;
        $up = (int) $row->up_count;
        $uptime = Metrics::ratio($up, $total, 3);

        $domains = $this->perDomain($filters);
        $currentStatus = $this->overallStatus($domains);

        return [
            'range' => [
                'date_from' => $filters->dateFromDate(),
                'date_to' => $filters->dateToDate(),
                'domain_id' => $filters->domainId,
            ],
            'overall' => [
                'total_checks' => $total,
                'up_checks' => $up,
                'down_checks' => (int) $row->down_count,
                'uptime_pct' => $uptime,
                'avg_response_ms' => round((float) $row->avg_resp, 1),
                'p95_response_ms' => round($p95, 1),
                'max_response_ms' => (int) $row->max_resp,
                'incidents' => (int) $row->down_count,
                'current_status' => $currentStatus,
                'status' => $this->statusFromUptime($uptime),
            ],
            'domains' => $domains,
            'timeseries' => $this->timeseries($filters),
        ];
    }

    public function checks(DashboardFilters $filters, ?string $status): LengthAwarePaginator
    {
        $query = $this->baseQuery($filters)
            ->with('domain')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('checked_at');

        $paginator = $query->paginate(perPage: $filters->perPage, page: $filters->page);

        $paginator->getCollection()->transform(fn (ServerCheck $c) => [
            'id' => $c->id,
            'domain' => $c->domain?->name,
            'checked_at' => $c->checked_at?->toIso8601String(),
            'status' => $c->status,
            'response_time_ms' => $c->response_time_ms,
            'http_status' => $c->http_status,
            'region' => $c->region,
            'error_message' => $c->error_message,
        ]);

        return $paginator;
    }

    /**
     * Per-domain uptime, average response, and latest (current) status.
     */
    private function perDomain(DashboardFilters $filters): array
    {
        $agg = $this->baseQuery($filters)
            ->join('domains', 'domains.id', '=', 'server_checks.domain_id')
            ->groupBy('domains.id', 'domains.name')
            ->select('domains.id as domain_id', 'domains.name as name')
            ->selectRaw("
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN server_checks.status = 'up' THEN 1 ELSE 0 END), 0) as up_count,
                COALESCE(AVG(response_time_ms) FILTER (WHERE server_checks.status = 'up'), 0) as avg_resp
            ")
            ->get();

        // Latest check per domain (Postgres DISTINCT ON).
        $latest = $this->baseQuery($filters)
            ->selectRaw('DISTINCT ON (domain_id) domain_id, status, checked_at')
            ->orderByRaw('domain_id, checked_at DESC')
            ->get()
            ->keyBy('domain_id');

        return $agg->map(function ($r) use ($latest) {
            $total = (int) $r->total;
            $uptime = Metrics::ratio((int) $r->up_count, $total, 3);
            $current = $latest->get($r->domain_id);

            return [
                'domain_id' => $r->domain_id,
                'domain' => $r->name,
                'uptime_pct' => $uptime,
                'avg_response_ms' => round((float) $r->avg_resp, 1),
                'current_status' => $current->status ?? 'unknown',
                'last_checked_at' => isset($current->checked_at)
                    ? \Carbon\CarbonImmutable::parse($current->checked_at)->toIso8601String()
                    : null,
                'status' => $this->statusFromUptime($uptime),
            ];
        })->all();
    }

    private function timeseries(DashboardFilters $filters): array
    {
        return $this->baseQuery($filters)
            ->selectRaw("
                date_trunc('day', checked_at) as day,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'up' THEN 1 ELSE 0 END) as up_count,
                AVG(response_time_ms) FILTER (WHERE status = 'up') as avg_resp
            ")
            ->groupByRaw("date_trunc('day', checked_at)")
            ->orderByRaw("date_trunc('day', checked_at)")
            ->get()
            ->map(fn ($r) => [
                'date' => substr((string) $r->day, 0, 10),
                'uptime_pct' => Metrics::ratio((int) $r->up_count, (int) $r->total, 3),
                'avg_response_ms' => round((float) $r->avg_resp, 1),
            ])->all();
    }

    private function overallStatus(array $domains): string
    {
        if ($domains === []) {
            return 'unknown';
        }

        foreach ($domains as $d) {
            if (($d['current_status'] ?? null) === 'down') {
                return 'down';
            }
        }

        return 'up';
    }

    private function statusFromUptime(float $uptime): string
    {
        return match (true) {
            $uptime < 99.0 => 'critical',
            $uptime < 99.9 => 'warning',
            default => 'healthy',
        };
    }

    private function baseQuery(DashboardFilters $filters): Builder
    {
        return ServerCheck::query()
            ->whereBetween('checked_at', [$filters->dateFrom, $filters->dateTo])
            ->when($filters->domainId, fn ($q) => $q->where('domain_id', $filters->domainId));
    }
}
