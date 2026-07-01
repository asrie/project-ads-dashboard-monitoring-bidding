<script lang="ts">
  import PageHeader from '../../lib/components/feedback/PageHeader.svelte';
  import GlobalFilter from '../../lib/components/forms/GlobalFilter.svelte';
  import StatCard from '../../lib/components/dashboard/StatCard.svelte';
  import Badge from '../../lib/components/feedback/Badge.svelte';
  import LineChart from '../../lib/components/charts/LineChart.svelte';
  import DataTable, { type Column } from '../../lib/components/tables/DataTable.svelte';
  import { getPrebidHealth, listAuctions } from '../../lib/api/prebid';
  import { filters, toQuery } from '../../lib/stores/filters';
  import { ApiException } from '../../lib/types/api';
  import type { PaginationMeta } from '../../lib/types/api';
  import type { PrebidHealth } from '../../lib/types/models';
  import { num, ms, percent, currency, dateTime } from '../../lib/utils/format';

  let health = $state<PrebidHealth | null>(null);
  let rows = $state<Record<string, unknown>[]>([]);
  let pagination = $state<PaginationMeta | undefined>(undefined);
  let loading = $state(true);
  let error = $state<string | null>(null);
  let lastUpdated = $state<string | null>(null);
  let page = $state(1);
  let statusFilter = $state('');

  const columns: Column[] = [
    { key: 'auction_id', label: 'Auction ID' },
    { key: 'domain', label: 'Domain' },
    { key: 'started_at', label: 'Waktu', format: (v) => dateTime(v as string) },
    { key: 'duration_ms', label: 'Durasi', align: 'right', format: (v) => ms(v as number) },
    { key: 'bidder_count', label: 'Bidders', align: 'right', format: (v) => num(v as number) },
    { key: 'bids_received', label: 'Bids', align: 'right', format: (v) => num(v as number) },
    { key: 'won_bidder', label: 'Pemenang' },
    { key: 'cpm', label: 'CPM', align: 'right', format: (v) => currency(v as number) },
    { key: 'status', label: 'Status', align: 'center', badge: true },
  ];

  async function load() {
    loading = true;
    error = null;
    try {
      const q = toQuery($filters);
      const [h, a] = await Promise.all([
        getPrebidHealth(q),
        listAuctions({ ...q, page, per_page: 20, status: statusFilter || undefined }),
      ]);
      health = h;
      rows = a.items;
      pagination = a.pagination;
      lastUpdated = new Date().toISOString();
    } catch (err) {
      error = err instanceof ApiException ? err.message : 'Gagal memuat data Prebid.';
    } finally {
      loading = false;
    }
  }

  function onPage(p: number) { page = p; load(); }
  function applyFilter() { page = 1; load(); }

  const chartLabels = $derived(health?.timeseries.map((t) => t.date) ?? []);
  const chartSeries = $derived(
    health
      ? [{ label: 'Avg Duration (ms)', color: '#2563EB', points: health.timeseries.map((t) => t.avg_duration_ms) }]
      : []
  );

  $effect(() => { load(); });
</script>

<PageHeader
  title="Prebid Health"
  description="Durasi auction, timeout, error, dan distribusi performa Prebid.js."
  {lastUpdated}
  {loading}
  onRefresh={load} />

<GlobalFilter onApply={applyFilter} />

{#if health}
  <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <StatCard label="Total Auction" value={num(health.total_auctions)} icon="⚡" />
    <StatCard label="Avg Duration" value={ms(health.avg_duration_ms)} status={health.status} sub="p95: {ms(health.p95_duration_ms)}" />
    <StatCard label="Timeout Rate" value={percent(health.timeout_rate)} icon="⏱️" />
    <StatCard label="Error Rate" value={percent(health.error_rate)} icon="❌" />
  </div>

  <div class="surface-card mt-4">
    <div class="card-body p-5">
      <div class="flex items-center justify-between">
        <h2 class="card-title text-base">Tren Durasi Auction</h2>
        <Badge status={health.status} />
      </div>
      <LineChart labels={chartLabels} series={chartSeries} valueFormat={(v) => Math.round(v) + 'ms'} />
    </div>
  </div>
{/if}

<div class="mb-3 mt-4 flex items-center gap-2">
  <h2 class="text-sm font-semibold">Auction Terbaru</h2>
  <select class="select select-bordered select-xs" bind:value={statusFilter} onchange={applyFilter}>
    <option value="">Semua status</option>
    <option value="completed">Completed</option>
    <option value="timeout">Timeout</option>
    <option value="error">Error</option>
  </select>
</div>

<DataTable {columns} {rows} {loading} {error} {pagination} {onPage} onRetry={load} />
