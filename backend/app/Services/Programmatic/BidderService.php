<?php

declare(strict_types=1);

namespace App\Services\Programmatic;

use App\Models\Bidder;
use App\Models\BidderPerformanceDaily;
use App\Support\DashboardFilters;
use App\Support\Metrics;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BidderService
{
    private const SORTABLE = ['name', 'revenue', 'bid_requests', 'bid_responses', 'timeout_rate', 'avg_latency_ms'];

    public function paginate(DashboardFilters $filters): LengthAwarePaginator
    {
        $agg = DB::table('bidder_performance_daily')
            ->select('bidder_id')
            ->selectRaw('SUM(bid_requests) as bid_requests, SUM(bid_responses) as bid_responses, SUM(bids_won) as bids_won, SUM(timeouts) as timeouts, SUM(errors) as errors, SUM(revenue) as revenue, AVG(avg_latency_ms) as avg_latency_ms, AVG(avg_cpm) as avg_cpm')
            ->whereBetween('date', [$filters->dateFrom->toDateString(), $filters->dateTo->toDateString()])
            ->when($filters->domainId, fn ($q) => $q->where('domain_id', $filters->domainId))
            ->groupBy('bidder_id');

        $sortBy = in_array($filters->sortBy, self::SORTABLE, true) ? $filters->sortBy : 'revenue';
        $sortExpr = match ($sortBy) {
            'name' => 'bidders.name',
            'timeout_rate' => 'COALESCE(agg.timeouts,0)::float / NULLIF(agg.bid_requests,0)',
            default => "COALESCE(agg.$sortBy,0)",
        };
        $sortDir = $filters->sortDir === 'asc' ? 'asc' : 'desc';

        $query = Bidder::query()
            ->leftJoinSub($agg, 'agg', 'agg.bidder_id', '=', 'bidders.id')
            ->select('bidders.*')
            ->selectRaw('COALESCE(agg.bid_requests,0) as a_req, COALESCE(agg.bid_responses,0) as a_res, COALESCE(agg.bids_won,0) as a_won, COALESCE(agg.timeouts,0) as a_to, COALESCE(agg.errors,0) as a_err, COALESCE(agg.revenue,0) as a_rev, COALESCE(agg.avg_latency_ms,0) as a_lat, COALESCE(agg.avg_cpm,0) as a_cpm')
            ->when($filters->search, fn ($q) => $q->where('bidders.name', 'ilike', '%'.$filters->search.'%'))
            ->orderByRaw("$sortExpr $sortDir NULLS LAST");

        $paginator = $query->paginate(perPage: $filters->perPage, page: $filters->page);

        $paginator->getCollection()->transform(fn (Bidder $b) => $this->mapBidder($b));

        return $paginator;
    }

    public function detail(string $bidderId, DashboardFilters $filters): array
    {
        /** @var Bidder $bidder */
        $bidder = Bidder::findOrFail($bidderId);

        $rows = BidderPerformanceDaily::query()
            ->where('bidder_id', $bidderId)
            ->whereBetween('date', [$filters->dateFrom->toDateString(), $filters->dateTo->toDateString()])
            ->when($filters->domainId, fn ($q) => $q->where('domain_id', $filters->domainId))
            ->selectRaw('date, SUM(bid_requests) as bid_requests, SUM(bid_responses) as bid_responses, SUM(bids_won) as bids_won, SUM(timeouts) as timeouts, SUM(errors) as errors, SUM(revenue) as revenue, AVG(avg_latency_ms) as avg_latency_ms, AVG(avg_cpm) as avg_cpm')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $req = (int) $rows->sum('bid_requests');
        $res = (int) $rows->sum('bid_responses');

        return [
            'bidder' => [
                'id' => $bidder->id,
                'name' => $bidder->name,
                'code' => $bidder->code,
            ],
            'totals' => [
                'bid_requests' => $req,
                'bid_responses' => $res,
                'bids_won' => (int) $rows->sum('bids_won'),
                'timeouts' => (int) $rows->sum('timeouts'),
                'errors' => (int) $rows->sum('errors'),
                'revenue' => round((float) $rows->sum('revenue'), 2),
                'bid_response_rate' => Metrics::ratio($res, $req),
                'timeout_rate' => Metrics::ratio((int) $rows->sum('timeouts'), $req),
                'no_bid_rate' => round(100 - Metrics::ratio($res, $req), 2),
                'win_rate' => Metrics::ratio((int) $rows->sum('bids_won'), $res),
                'avg_latency_ms' => round((float) $rows->avg('avg_latency_ms'), 1),
                'avg_cpm' => round((float) $rows->avg('avg_cpm'), 2),
            ],
            'timeseries' => $rows->map(fn ($r) => [
                'date' => (string) $r->date,
                'bid_requests' => (int) $r->bid_requests,
                'bid_responses' => (int) $r->bid_responses,
                'timeout_rate' => Metrics::ratio((int) $r->timeouts, (int) $r->bid_requests),
                'bid_response_rate' => Metrics::ratio((int) $r->bid_responses, (int) $r->bid_requests),
                'avg_latency_ms' => round((float) $r->avg_latency_ms, 1),
                'revenue' => round((float) $r->revenue, 2),
            ])->all(),
        ];
    }

    private function mapBidder(Bidder $b): array
    {
        $req = (int) ($b->a_req ?? 0);
        $res = (int) ($b->a_res ?? 0);
        $to = (int) ($b->a_to ?? 0);
        $timeoutRate = Metrics::ratio($to, $req);

        return [
            'id' => $b->id,
            'name' => $b->name,
            'code' => $b->code,
            'bid_requests' => $req,
            'bid_responses' => $res,
            'bids_won' => (int) ($b->a_won ?? 0),
            'timeouts' => $to,
            'errors' => (int) ($b->a_err ?? 0),
            'revenue' => round((float) ($b->a_rev ?? 0), 2),
            'bid_response_rate' => Metrics::ratio($res, $req),
            'timeout_rate' => $timeoutRate,
            'no_bid_rate' => round(100 - Metrics::ratio($res, $req), 2),
            'win_rate' => Metrics::ratio((int) ($b->a_won ?? 0), $res),
            'avg_latency_ms' => round((float) ($b->a_lat ?? 0), 1),
            'avg_cpm' => round((float) ($b->a_cpm ?? 0), 2),
            'health' => $this->health($timeoutRate, $res, $req),
        ];
    }

    private function health(float $timeoutRate, int $responses, int $requests): string
    {
        $responseRate = Metrics::ratio($responses, $requests);

        if ($timeoutRate > 25 || $responseRate < 20) {
            return 'critical';
        }
        if ($timeoutRate > 10 || $responseRate < 40) {
            return 'warning';
        }

        return 'healthy';
    }
}
