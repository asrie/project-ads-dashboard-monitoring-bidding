<?php

declare(strict_types=1);

namespace App\Services\NetworkAds;

use App\Models\NetworkAdRequest;
use App\Support\DashboardFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NetworkAdsService
{
    public function paginate(DashboardFilters $filters, ?string $type, ?bool $thirdParty): LengthAwarePaginator
    {
        $query = $this->baseQuery($filters)
            ->with('domain')
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($thirdParty !== null, fn ($q) => $q->where('is_third_party', $thirdParty))
            ->when($filters->search, fn ($q) => $q->where('resource_url', 'ilike', '%'.$filters->search.'%'))
            ->orderByDesc('duration_ms');

        $paginator = $query->paginate(perPage: $filters->perPage, page: $filters->page);
        $paginator->getCollection()->transform(fn (NetworkAdRequest $r) => $this->map($r));

        return $paginator;
    }

    /** Heavy requests: largest / slowest third-party resources. */
    public function heavyRequests(DashboardFilters $filters): array
    {
        $base = $this->baseQuery($filters);

        $summary = (clone $base)->selectRaw('
            COUNT(*) as total,
            COALESCE(AVG(duration_ms),0) as avg_duration,
            COALESCE(SUM(size_bytes),0) as total_bytes,
            COALESCE(SUM(CASE WHEN is_blocking THEN 1 ELSE 0 END),0) as blocking,
            COALESCE(SUM(CASE WHEN is_third_party THEN 1 ELSE 0 END),0) as third_party
        ')->first();

        $byVendor = (clone $base)
            ->selectRaw('vendor, COUNT(*) as requests, COALESCE(SUM(size_bytes),0) as total_bytes, COALESCE(AVG(duration_ms),0) as avg_duration')
            ->whereNotNull('vendor')
            ->groupBy('vendor')
            ->orderByDesc('total_bytes')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'vendor' => $r->vendor,
                'requests' => (int) $r->requests,
                'total_bytes' => (int) $r->total_bytes,
                'avg_duration_ms' => round((float) $r->avg_duration, 1),
            ])->all();

        $heaviest = (clone $base)
            ->orderByDesc('size_bytes')
            ->limit(20)
            ->get()
            ->map(fn (NetworkAdRequest $r) => $this->map($r))
            ->all();

        return [
            'summary' => [
                'total_requests' => (int) $summary->total,
                'avg_duration_ms' => round((float) $summary->avg_duration, 1),
                'total_bytes' => (int) $summary->total_bytes,
                'blocking_requests' => (int) $summary->blocking,
                'third_party_requests' => (int) $summary->third_party,
            ],
            'by_vendor' => $byVendor,
            'heaviest' => $heaviest,
        ];
    }

    private function map(NetworkAdRequest $r): array
    {
        return [
            'id' => $r->id,
            'domain' => $r->domain?->name,
            'device' => $r->device,
            'observed_at' => $r->observed_at?->toIso8601String(),
            'resource_url' => $r->resource_url,
            'vendor' => $r->vendor,
            'type' => $r->type,
            'size_bytes' => $r->size_bytes,
            'size_kb' => round($r->size_bytes / 1024, 1),
            'duration_ms' => $r->duration_ms,
            'is_third_party' => $r->is_third_party,
            'is_blocking' => $r->is_blocking,
            'status_code' => $r->status_code,
        ];
    }

    private function baseQuery(DashboardFilters $filters)
    {
        return NetworkAdRequest::query()
            ->whereBetween('observed_at', [$filters->dateFrom, $filters->dateTo])
            ->when($filters->domainId, fn ($q) => $q->where('domain_id', $filters->domainId))
            ->when($filters->pageId, fn ($q) => $q->where('page_id', $filters->pageId))
            ->when($filters->device, fn ($q) => $q->where('device', $filters->device));
    }
}
