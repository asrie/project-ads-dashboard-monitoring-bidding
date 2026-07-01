<script lang="ts">
  import PageHeader from '../../lib/components/feedback/PageHeader.svelte';
  import GlobalFilter from '../../lib/components/forms/GlobalFilter.svelte';
  import StatCard from '../../lib/components/dashboard/StatCard.svelte';
  import Badge from '../../lib/components/feedback/Badge.svelte';
  import LineChart from '../../lib/components/charts/LineChart.svelte';
  import LoadingState from '../../lib/components/feedback/LoadingState.svelte';
  import ErrorState from '../../lib/components/feedback/ErrorState.svelte';
  import { getOverview } from '../../lib/api/dashboard';
  import { filters, toQuery } from '../../lib/stores/filters';
  import { ApiException } from '../../lib/types/api';
  import type { DashboardOverview } from '../../lib/types/models';
  import { currency, num, percent, ms } from '../../lib/utils/format';

  let data = $state<DashboardOverview | null>(null);
  let loading = $state(true);
  let error = $state<string | null>(null);
  let lastUpdated = $state<string | null>(null);

  async function load() {
    loading = true;
    error = null;
    try {
      data = await getOverview(toQuery($filters));
      lastUpdated = new Date().toISOString();
    } catch (err) {
      error = err instanceof ApiException ? err.message : 'Gagal memuat overview.';
    } finally {
      loading = false;
    }
  }

  $effect(() => {
    load();
  });

  const trendLabels = $derived(data?.revenue_trend.map((r) => r.date) ?? []);
  const trendSeries = $derived(
    data
      ? [{ label: 'Revenue', color: '#1E3A8A', points: data.revenue_trend.map((r) => r.revenue) }]
      : []
  );
</script>

<PageHeader
  title="Dashboard Overview"
  description="Ringkasan revenue, demand, kesehatan Prebid/GAM, Web Vitals, dan alert aktif."
  {lastUpdated}
  {loading}
  onRefresh={load} />

<GlobalFilter onApply={load} />

{#if loading && !data}
  <LoadingState />
{:else if error}
  <ErrorState message={error} onRetry={load} />
{:else if data}
  <!-- Revenue & demand KPIs -->
  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <StatCard label="Total Revenue" value={currency(data.revenue.total_revenue)} sub="Periode terpilih" icon="💵" />
    <StatCard label="Impressions" value={num(data.revenue.total_impressions)} sub="Total tayang iklan" icon="👁️" />
    <StatCard label="Fill Rate" value={percent(data.revenue.fill_rate)} sub="Impression / Ad request" icon="📦" />
    <StatCard label="Avg eCPM" value={currency(data.revenue.avg_ecpm)} sub="Per 1000 impression" icon="📈" />
    <StatCard label="Bid Requests" value={num(data.demand.bid_requests)} icon="📨" />
    <StatCard label="Bid Response Rate" value={percent(data.demand.bid_response_rate)} icon="✅" />
    <StatCard label="Timeout Rate" value={percent(data.demand.timeout_rate)} status={data.demand.timeout_rate > 25 ? 'critical' : data.demand.timeout_rate > 10 ? 'warning' : 'healthy'} icon="⏱️" />
    <StatCard label="No-Bid Rate" value={percent(data.demand.no_bid_rate)} icon="🚫" />
  </div>

  <!-- Health row -->
  <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="surface-card">
      <div class="card-body p-5">
        <div class="flex items-center justify-between">
          <h2 class="card-title text-base">Prebid Health</h2>
          <Badge status={data.prebid.status} />
        </div>
        <dl class="mt-2 space-y-1 text-sm">
          <div class="flex justify-between"><dt class="text-base-content/60">Total Auction</dt><dd class="num">{num(data.prebid.total_auctions)}</dd></div>
          <div class="flex justify-between"><dt class="text-base-content/60">Avg Duration</dt><dd class="num">{ms(data.prebid.avg_duration_ms)}</dd></div>
          <div class="flex justify-between"><dt class="text-base-content/60">Timeout</dt><dd class="num">{num(data.prebid.timeout_count)}</dd></div>
          <div class="flex justify-between"><dt class="text-base-content/60">Error</dt><dd class="num">{num(data.prebid.error_count)}</dd></div>
        </dl>
      </div>
    </div>

    <div class="surface-card">
      <div class="card-body p-5">
        <div class="flex items-center justify-between">
          <h2 class="card-title text-base">GAM Health</h2>
          <Badge status={data.gam.status} />
        </div>
        <dl class="mt-2 space-y-1 text-sm">
          <div class="flex justify-between"><dt class="text-base-content/60">Total Request</dt><dd class="num">{num(data.gam.total_requests)}</dd></div>
          <div class="flex justify-between"><dt class="text-base-content/60">Success Rate</dt><dd class="num">{percent(data.gam.success_rate)}</dd></div>
          <div class="flex justify-between"><dt class="text-base-content/60">Failure Rate</dt><dd class="num">{percent(data.gam.failure_rate)}</dd></div>
          <div class="flex justify-between"><dt class="text-base-content/60">Avg Latency</dt><dd class="num">{ms(data.gam.avg_latency_ms)}</dd></div>
        </dl>
      </div>
    </div>

    <div class="surface-card">
      <div class="card-body p-5">
        <div class="flex items-center justify-between">
          <h2 class="card-title text-base">Active Alerts</h2>
          <span class="badge badge-error badge-lg">{data.alerts.active_total}</span>
        </div>
        <dl class="mt-2 space-y-1 text-sm">
          <div class="flex justify-between"><dt class="text-base-content/60">Critical</dt><dd class="num text-error">{data.alerts.critical}</dd></div>
          <div class="flex justify-between"><dt class="text-base-content/60">High</dt><dd class="num">{data.alerts.high}</dd></div>
          <div class="flex justify-between"><dt class="text-base-content/60">Medium</dt><dd class="num">{data.alerts.medium}</dd></div>
          <div class="flex justify-between"><dt class="text-base-content/60">Low</dt><dd class="num">{data.alerts.low}</dd></div>
        </dl>
      </div>
    </div>
  </div>

  <!-- Revenue trend -->
  <div class="surface-card mt-4">
    <div class="card-body p-5">
      <h2 class="card-title text-base">Revenue Trend</h2>
      <LineChart labels={trendLabels} series={trendSeries} valueFormat={(v) => '$' + Math.round(v)} />
    </div>
  </div>

  <!-- Web vitals quick glance -->
  <div class="surface-card mt-4">
    <div class="card-body p-5">
      <h2 class="card-title text-base">Web Core Vitals (rata-rata)</h2>
      <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        {#each ['lcp', 'inp', 'cls', 'fcp', 'ttfb', 'tbt'] as key}
          <div class="rounded-lg bg-base-200 p-3 text-center">
            <p class="text-xs uppercase text-base-content/50">{key}</p>
            <p class="text-lg font-bold text-base-content">{num(data.web_vitals[key] ?? 0)}</p>
          </div>
        {/each}
      </div>
    </div>
  </div>
{/if}
