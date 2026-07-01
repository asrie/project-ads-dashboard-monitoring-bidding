<?php

declare(strict_types=1);

namespace App\Services\Programmatic;

use App\Models\AdSlot;
use App\Models\SlotPerformanceDaily;
use App\Support\DashboardFilters;
use App\Support\Metrics;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SlotService
{
    private const SORTABLE = ['name', 'revenue', 'impressions', 'ad_requests', 'fill_rate', 'ecpm'];

    public function paginate(DashboardFilters $filters): LengthAwarePaginator
    {
        $agg = DB::table('slot_performance_daily')
            ->select('slot_id')
            ->selectRaw('SUM(revenue) as revenue, SUM(impressions) as impressions, SUM(ad_requests) as ad_requests, AVG(viewability) as viewability')
            ->whereBetween('date', [$filters->dateFrom->toDateString(), $filters->dateTo->toDateString()])
            ->when($filters->device, fn ($q) => $q->where('device', $filters->device))
            ->when($filters->domainId, fn ($q) => $q->where('domain_id', $filters->domainId))
            ->groupBy('slot_id');

        $sortBy = in_array($filters->sortBy, self::SORTABLE, true) ? $filters->sortBy : 'revenue';
        $sortExpr = match ($sortBy) {
            'name' => 'ad_slots.name',
            'fill_rate' => 'COALESCE(agg.impressions,0)::float / NULLIF(agg.ad_requests,0)',
            'ecpm' => 'COALESCE(agg.revenue,0) / NULLIF(agg.impressions,0)',
            default => "COALESCE(agg.$sortBy,0)",
        };
        $sortDir = $filters->sortDir === 'asc' ? 'asc' : 'desc';

        $query = AdSlot::query()
            ->with('domain')
            ->leftJoinSub($agg, 'agg', 'agg.slot_id', '=', 'ad_slots.id')
            ->select('ad_slots.*')
            ->selectRaw('COALESCE(agg.revenue,0) as agg_revenue, COALESCE(agg.impressions,0) as agg_impressions, COALESCE(agg.ad_requests,0) as agg_ad_requests, COALESCE(agg.viewability,0) as agg_viewability')
            ->when($filters->domainId, fn ($q) => $q->where('ad_slots.domain_id', $filters->domainId))
            ->when($filters->search, fn ($q) => $q->where('ad_slots.name', 'ilike', '%'.$filters->search.'%'))
            ->orderByRaw("$sortExpr $sortDir NULLS LAST");

        $paginator = $query->paginate(perPage: $filters->perPage, page: $filters->page);

        $paginator->getCollection()->transform(function (AdSlot $slot) {
            return $this->mapSlot($slot);
        });

        return $paginator;
    }

    public function detail(string $slotId, DashboardFilters $filters): array
    {
        /** @var AdSlot $slot */
        $slot = AdSlot::with('domain')->findOrFail($slotId);

        $rows = SlotPerformanceDaily::query()
            ->where('slot_id', $slotId)
            ->whereBetween('date', [$filters->dateFrom->toDateString(), $filters->dateTo->toDateString()])
            ->when($filters->device, fn ($q) => $q->where('device', $filters->device))
            ->selectRaw('date, SUM(revenue) as revenue, SUM(impressions) as impressions, SUM(ad_requests) as ad_requests, AVG(viewability) as viewability')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $revenue = (float) $rows->sum('revenue');
        $impressions = (int) $rows->sum('impressions');
        $adRequests = (int) $rows->sum('ad_requests');

        return [
            'slot' => [
                'id' => $slot->id,
                'name' => $slot->name,
                'ad_unit_path' => $slot->ad_unit_path,
                'sizes' => $slot->sizes,
                'device' => $slot->device,
                'domain' => $slot->domain?->name,
            ],
            'totals' => [
                'revenue' => round($revenue, 2),
                'impressions' => $impressions,
                'ad_requests' => $adRequests,
                'fill_rate' => Metrics::ratio($impressions, $adRequests),
                'ecpm' => Metrics::ecpm($revenue, $impressions),
                'viewability' => round((float) $rows->avg('viewability'), 1),
            ],
            'timeseries' => $rows->map(fn ($r) => [
                'date' => (string) $r->date,
                'revenue' => round((float) $r->revenue, 2),
                'impressions' => (int) $r->impressions,
                'ad_requests' => (int) $r->ad_requests,
                'fill_rate' => Metrics::ratio((int) $r->impressions, (int) $r->ad_requests),
                'ecpm' => Metrics::ecpm((float) $r->revenue, (int) $r->impressions),
            ])->all(),
        ];
    }

    private function mapSlot(AdSlot $slot): array
    {
        $revenue = (float) ($slot->agg_revenue ?? 0);
        $impressions = (int) ($slot->agg_impressions ?? 0);
        $adRequests = (int) ($slot->agg_ad_requests ?? 0);

        return [
            'id' => $slot->id,
            'name' => $slot->name,
            'ad_unit_path' => $slot->ad_unit_path,
            'device' => $slot->device,
            'domain' => $slot->domain?->name,
            'revenue' => round($revenue, 2),
            'impressions' => $impressions,
            'ad_requests' => $adRequests,
            'fill_rate' => Metrics::ratio($impressions, $adRequests),
            'ecpm' => Metrics::ecpm($revenue, $impressions),
            'viewability' => round((float) ($slot->agg_viewability ?? 0), 1),
        ];
    }
}
