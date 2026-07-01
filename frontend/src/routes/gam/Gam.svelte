<script lang="ts">
  import PageHeader from '../../lib/components/feedback/PageHeader.svelte';
  import GlobalFilter from '../../lib/components/forms/GlobalFilter.svelte';
  import StatCard from '../../lib/components/dashboard/StatCard.svelte';
  import Badge from '../../lib/components/feedback/Badge.svelte';
  import LineChart from '../../lib/components/charts/LineChart.svelte';
  import DataTable, { type Column } from '../../lib/components/tables/DataTable.svelte';
  import { getGamHealth, listGamRequests } from '../../lib/api/gam';
  import { filters, toQuery } from '../../lib/stores/filters';
  import { ApiException } from '../../lib/types/api';
  import type { PaginationMeta } from '../../lib/types/api';
  import type { GamHealth } from '../../lib/types/models';
  import { num, ms, percent, dateTime } from '../../lib/utils/format';

  let health = $state<GamHealth | null>(null);
  let rows = $state<Record<string, unknown>[]>([]);
  let pagination = $state<PaginationMeta | undefined>(undefined);
  let loading = $state(true);
  let error = $state<string | null>(null);
  let lastUpdated = $state<string | null>(null);
  let page = $state(1);
  let statusFilter = $state('');

  const columns: Column[] = [
    { key: 'domain', label: 'Domain' },
    { key: 'ad_unit', label: 'Ad Unit' },
    { key: 'requested_at', label: 'Waktu', format: (v) => dateTime(v as string) },
    { key: 'status', label: 'Status', align: 'center', badge: true },
    { key: 'latency_ms', label: 'Latency', align: 'right', format: (v) => ms(v as number) },
    { key: 'http_status', label: 'HTTP', align: 'right' },
    { key: 'line_item_id', label: 'Line Item' },
  ];

  async function load() {
    loading = true;
    error = null;
    try {
      const q = toQuery($filters);
      const [h, r] = await Promise.all([
        getGamHealth(q),
        listGamRequests({ ...q, page, per_page: 20, status: statusFilter || undefined }),
      ]);
      health = h;
      rows = r.items;
      pagination = r.pagination;
      lastUpdated = new Date().toISOString();
    } catch (err) {
      error = err instanceof ApiException ? err.message : 'Gagal memuat data GAM.';
    } finally {
      loading = false;
    }
  }

  function onPage(p: number) { page = p; load(); }
  function applyFilter() { page = 1; load(); }

  const chartLabels = $derived(health?.timeseries.map((t) => t.date) ?? []);
  const chartSeries = $derived(
    health
      ? [{ label: 'Failure Rate (%)', color: '#DC2626', points: health.timeseries.map((t) => t.failure_rate) }]
      : []
  );

  $effect(() => { load(); });
</script>

<PageHeader
  title="GAM Monitoring"
  description="Kesehatan koneksi Google Ad Manager: success/failure rate dan latency."
  {lastUpdated}
  {loading}
  onRefresh={load} />

<GlobalFilter onApply={applyFilter} />

{#if health}
  <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <StatCard label="Total Request" value={num(health.total_requests)} icon="🛰️" />
    <StatCard label="Success Rate" value={percent(health.success_rate)} status={health.status} />
    <StatCard label="Failure Rate" value={percent(health.failure_rate)} status={health.status} icon="❌" />
    <StatCard label="Avg Latency" value={ms(health.avg_latency_ms)} icon="⏱️" />
  </div>

  <div class="surface-card mt-4">
    <div class="card-body p-5">
      <div class="flex items-center justify-between">
        <h2 class="card-title text-base">Tren Failure Rate</h2>
        <Badge status={health.status} />
      </div>
      <LineChart labels={chartLabels} series={chartSeries} valueFormat={(v) => v.toFixed(1) + '%'} />
    </div>
  </div>
{/if}

<div class="mb-3 mt-4 flex items-center gap-2">
  <h2 class="text-sm font-semibold">Request Terbaru</h2>
  <select class="select select-bordered select-xs" bind:value={statusFilter} onchange={applyFilter}>
    <option value="">Semua status</option>
    <option value="success">Success</option>
    <option value="empty">Empty</option>
    <option value="failed">Failed</option>
  </select>
</div>

<DataTable {columns} {rows} {loading} {error} {pagination} {onPage} onRetry={load} />
