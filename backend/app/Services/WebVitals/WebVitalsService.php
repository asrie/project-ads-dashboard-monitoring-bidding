<?php

declare(strict_types=1);

namespace App\Services\WebVitals;

use App\Models\WebVitalDaily;
use App\Support\DashboardFilters;
use App\Support\Metrics;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WebVitalsService
{
    /** Core Web Vitals thresholds (CLAUDE.md Alert Rules). */
    private const THRESHOLDS = [
        'lcp' => ['warn' => 2500, 'critical' => 4000],
        'inp' => ['warn' => 200, 'critical' => 500],
        'cls' => ['warn' => 0.1, 'critical' => 0.25],
        'fcp' => ['warn' => 1800, 'critical' => 3000],
        'ttfb' => ['warn' => 800, 'critical' => 1800],
        'tbt' => ['warn' => 200, 'critical' => 600],
    ];

    public function summary(DashboardFilters $filters): array
    {
        $base = $this->baseQuery($filters);

        $row = (clone $base)->selectRaw('
            COALESCE(SUM(samples),0) as samples,
            COALESCE(SUM(lcp*samples),0) as lcp,
            COALESCE(SUM(inp*samples),0) as inp,
            COALESCE(SUM(cls*samples),0) as cls,
            COALESCE(SUM(fcp*samples),0) as fcp,
            COALESCE(SUM(ttfb*samples),0) as ttfb,
            COALESCE(SUM(tbt*samples),0) as tbt
        ')->first();

        $samples = (int) $row->samples;

        $metrics = [
            'lcp' => Metrics::div((float) $row->lcp, $samples, 1),
            'inp' => Metrics::div((float) $row->inp, $samples, 1),
            'cls' => Metrics::div((float) $row->cls, $samples, 3),
            'fcp' => Metrics::div((float) $row->fcp, $samples, 1),
            'ttfb' => Metrics::div((float) $row->ttfb, $samples, 1),
            'tbt' => Metrics::div((float) $row->tbt, $samples, 1),
        ];

        $metricsWithStatus = [];
        foreach ($metrics as $key => $value) {
            $metricsWithStatus[$key] = [
                'value' => $value,
                'status' => $this->rate($key, $value),
                'unit' => $key === 'cls' ? '' : 'ms',
            ];
        }

        $timeseries = (clone $base)
            ->selectRaw('date,
                SUM(samples) as samples,
                SUM(lcp*samples) as lcp, SUM(inp*samples) as inp, SUM(cls*samples) as cls,
                SUM(fcp*samples) as fcp, SUM(ttfb*samples) as ttfb, SUM(tbt*samples) as tbt')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($r) {
                $s = (int) $r->samples;

                return [
                    'date' => (string) $r->date,
                    'lcp' => Metrics::div((float) $r->lcp, $s, 1),
                    'inp' => Metrics::div((float) $r->inp, $s, 1),
                    'cls' => Metrics::div((float) $r->cls, $s, 3),
                    'fcp' => Metrics::div((float) $r->fcp, $s, 1),
                    'ttfb' => Metrics::div((float) $r->ttfb, $s, 1),
                    'tbt' => Metrics::div((float) $r->tbt, $s, 1),
                ];
            })->all();

        return [
            'samples' => $samples,
            'metrics' => $metricsWithStatus,
            'timeseries' => $timeseries,
        ];
    }

    public function pages(DashboardFilters $filters): LengthAwarePaginator
    {
        $query = WebVitalDaily::query()
            ->join('pages', 'pages.id', '=', 'web_vitals_daily.page_id')
            ->whereBetween('web_vitals_daily.date', [$filters->dateFrom->toDateString(), $filters->dateTo->toDateString()])
            ->when($filters->domainId, fn ($q) => $q->where('web_vitals_daily.domain_id', $filters->domainId))
            ->when($filters->device, fn ($q) => $q->where('web_vitals_daily.device', $filters->device))
            ->when($filters->search, fn ($q) => $q->where('pages.path', 'ilike', '%'.$filters->search.'%'))
            ->groupBy('pages.id', 'pages.path', 'pages.title')
            ->select('pages.id as page_id', 'pages.path', 'pages.title')
            ->selectRaw('SUM(web_vitals_daily.samples) as samples,
                SUM(lcp*web_vitals_daily.samples) as lcp, SUM(inp*web_vitals_daily.samples) as inp,
                SUM(cls*web_vitals_daily.samples) as cls, SUM(fcp*web_vitals_daily.samples) as fcp,
                SUM(ttfb*web_vitals_daily.samples) as ttfb, SUM(tbt*web_vitals_daily.samples) as tbt')
            ->orderByDesc('samples');

        $paginator = $query->paginate(perPage: $filters->perPage, page: $filters->page);

        $paginator->getCollection()->transform(function ($r) {
            $s = (int) $r->samples;
            $lcp = Metrics::div((float) $r->lcp, $s, 1);

            return [
                'page_id' => $r->page_id,
                'path' => $r->path,
                'title' => $r->title,
                'samples' => $s,
                'lcp' => $lcp,
                'inp' => Metrics::div((float) $r->inp, $s, 1),
                'cls' => Metrics::div((float) $r->cls, $s, 3),
                'fcp' => Metrics::div((float) $r->fcp, $s, 1),
                'ttfb' => Metrics::div((float) $r->ttfb, $s, 1),
                'tbt' => Metrics::div((float) $r->tbt, $s, 1),
                'lcp_status' => $this->rate('lcp', $lcp),
            ];
        });

        return $paginator;
    }

    private function rate(string $metric, float $value): string
    {
        $t = self::THRESHOLDS[$metric];

        return match (true) {
            $value >= $t['critical'] => 'poor',
            $value >= $t['warn'] => 'needs_improvement',
            default => 'good',
        };
    }

    private function baseQuery(DashboardFilters $filters)
    {
        return WebVitalDaily::query()
            ->whereBetween('date', [$filters->dateFrom->toDateString(), $filters->dateTo->toDateString()])
            ->when($filters->domainId, fn ($q) => $q->where('domain_id', $filters->domainId))
            ->when($filters->pageId, fn ($q) => $q->where('page_id', $filters->pageId))
            ->when($filters->device, fn ($q) => $q->where('device', $filters->device));
    }
}
