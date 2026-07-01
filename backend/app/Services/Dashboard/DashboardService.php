<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\AlertStatus;
use App\Models\Alert;
use App\Models\BidderPerformanceDaily;
use App\Models\GamRequest;
use App\Models\PrebidAuction;
use App\Models\SlotPerformanceDaily;
use App\Models\WebVitalDaily;
use App\Support\DashboardFilters;
use App\Support\Metrics;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    public function getOverview(DashboardFilters $filters): array
    {
        return [
            'range' => [
                'date_from' => $filters->dateFromDate(),
                'date_to' => $filters->dateToDate(),
                'device' => $filters->device,
                'domain_id' => $filters->domainId,
            ],
            'revenue' => $this->revenueBlock($filters),
            'demand' => $this->demandBlock($filters),
            'prebid' => $this->prebidHealth($filters),
            'gam' => $this->gamHealth($filters),
            'web_vitals' => $this->webVitalsSummary($filters),
            'alerts' => $this->alertSummary($filters),
            'revenue_trend' => $this->revenueTrend($filters),
        ];
    }

    private function revenueBlock(DashboardFilters $filters): array
    {
        $row = $this->applyDateDomainDevice(
            SlotPerformanceDaily::query(), $filters, 'date'
        )->selectRaw('
            COALESCE(SUM(revenue),0) as revenue,
            COALESCE(SUM(impressions),0) as impressions,
            COALESCE(SUM(ad_requests),0) as ad_requests
        ')->first();

        $revenue = (float) $row->revenue;
        $impressions = (int) $row->impressions;
        $adRequests = (int) $row->ad_requests;

        return [
            'total_revenue' => round($revenue, 2),
            'total_impressions' => $impressions,
            'total_ad_requests' => $adRequests,
            'fill_rate' => Metrics::ratio($impressions, $adRequests),
            'avg_ecpm' => Metrics::ecpm($revenue, $impressions),
        ];
    }

    private function demandBlock(DashboardFilters $filters): array
    {
        $row = $this->applyDateDomainDevice(
            BidderPerformanceDaily::query(), $filters, 'date', withDevice: false
        )->selectRaw('
            COALESCE(SUM(bid_requests),0) as bid_requests,
            COALESCE(SUM(bid_responses),0) as bid_responses,
            COALESCE(SUM(bids_won),0) as bids_won,
            COALESCE(SUM(timeouts),0) as timeouts
        ')->first();

        $req = (int) $row->bid_requests;
        $res = (int) $row->bid_responses;

        return [
            'bid_requests' => $req,
            'bid_responses' => $res,
            'bids_won' => (int) $row->bids_won,
            'bid_response_rate' => Metrics::ratio($res, $req),
            'timeout_rate' => Metrics::ratio((int) $row->timeouts, $req),
            'no_bid_rate' => round(100 - Metrics::ratio($res, $req), 2),
        ];
    }

    private function prebidHealth(DashboardFilters $filters): array
    {
        $row = $this->applyDateDomainDevice(
            PrebidAuction::query(), $filters, 'started_at'
        )->selectRaw('
            COUNT(*) as total,
            COALESCE(AVG(duration_ms),0) as avg_duration,
            COALESCE(SUM(timeouts),0) as timeouts,
            COALESCE(SUM(errors),0) as errors,
            COALESCE(AVG(bidder_count),0) as avg_bidders
        ')->first();

        $total = (int) $row->total;
        $avgDuration = round((float) $row->avg_duration, 1);

        return [
            'total_auctions' => $total,
            'avg_duration_ms' => $avgDuration,
            'timeout_count' => (int) $row->timeouts,
            'error_count' => (int) $row->errors,
            'avg_bidders' => round((float) $row->avg_bidders, 1),
            'status' => $this->healthStatus($avgDuration, 1000, 2000),
        ];
    }

    private function gamHealth(DashboardFilters $filters): array
    {
        $row = $this->applyDateDomainDevice(
            GamRequest::query(), $filters, 'requested_at'
        )->selectRaw("
            COUNT(*) as total,
            COALESCE(SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END),0) as success,
            COALESCE(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END),0) as failed,
            COALESCE(AVG(latency_ms),0) as avg_latency
        ")->first();

        $total = (int) $row->total;
        $failed = (int) $row->failed;
        $failureRate = Metrics::ratio($failed, $total);

        return [
            'total_requests' => $total,
            'success_requests' => (int) $row->success,
            'failed_requests' => $failed,
            'success_rate' => Metrics::ratio((int) $row->success, $total),
            'failure_rate' => $failureRate,
            'avg_latency_ms' => round((float) $row->avg_latency, 1),
            'status' => $this->healthStatusAsc($failureRate, 5, 15),
        ];
    }

    private function webVitalsSummary(DashboardFilters $filters): array
    {
        $row = $this->applyDateDomainDevice(
            WebVitalDaily::query(), $filters, 'date'
        )->selectRaw('
            COALESCE(SUM(samples),0) as samples,
            COALESCE(SUM(lcp*samples),0) as lcp,
            COALESCE(SUM(inp*samples),0) as inp,
            COALESCE(SUM(cls*samples),0) as cls,
            COALESCE(SUM(fcp*samples),0) as fcp,
            COALESCE(SUM(ttfb*samples),0) as ttfb,
            COALESCE(SUM(tbt*samples),0) as tbt
        ')->first();

        $samples = (int) $row->samples;

        return [
            'samples' => $samples,
            'lcp' => Metrics::div((float) $row->lcp, $samples, 1),
            'inp' => Metrics::div((float) $row->inp, $samples, 1),
            'cls' => Metrics::div((float) $row->cls, $samples, 3),
            'fcp' => Metrics::div((float) $row->fcp, $samples, 1),
            'ttfb' => Metrics::div((float) $row->ttfb, $samples, 1),
            'tbt' => Metrics::div((float) $row->tbt, $samples, 1),
        ];
    }

    private function alertSummary(DashboardFilters $filters): array
    {
        $query = Alert::query()->where('status', AlertStatus::Open->value);
        if ($filters->domainId) {
            $query->where('domain_id', $filters->domainId);
        }

        $bySeverity = (clone $query)
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        return [
            'active_total' => (int) $query->count(),
            'critical' => (int) ($bySeverity['critical'] ?? 0),
            'high' => (int) ($bySeverity['high'] ?? 0),
            'medium' => (int) ($bySeverity['medium'] ?? 0),
            'low' => (int) ($bySeverity['low'] ?? 0),
        ];
    }

    private function revenueTrend(DashboardFilters $filters): array
    {
        $rows = $this->applyDateDomainDevice(
            SlotPerformanceDaily::query(), $filters, 'date'
        )
            ->selectRaw('date, COALESCE(SUM(revenue),0) as revenue, COALESCE(SUM(impressions),0) as impressions')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $rows->map(fn ($r) => [
            'date' => (string) $r->date,
            'revenue' => round((float) $r->revenue, 2),
            'impressions' => (int) $r->impressions,
        ])->all();
    }

    /**
     * Apply common date-range + domain + device filters to a query.
     */
    private function applyDateDomainDevice(Builder $query, DashboardFilters $filters, string $dateColumn, bool $withDevice = true): Builder
    {
        $query->whereBetween($dateColumn, [$filters->dateFrom, $filters->dateTo]);

        if ($filters->domainId) {
            $query->where('domain_id', $filters->domainId);
        }

        if ($withDevice && $filters->device) {
            $query->where('device', $filters->device);
        }

        return $query;
    }

    /** Lower is better (e.g. duration ms). */
    private function healthStatus(float $value, float $warn, float $critical): string
    {
        return match (true) {
            $value >= $critical => 'critical',
            $value >= $warn => 'warning',
            default => 'healthy',
        };
    }

    /** Lower is better, thresholds ascending percentages. */
    private function healthStatusAsc(float $value, float $warn, float $critical): string
    {
        return $this->healthStatus($value, $warn, $critical);
    }
}
