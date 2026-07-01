<script lang="ts">
  import PageHeader from '../../lib/components/feedback/PageHeader.svelte';
  import GlobalFilter from '../../lib/components/forms/GlobalFilter.svelte';
  import StatCard from '../../lib/components/dashboard/StatCard.svelte';
  import Badge from '../../lib/components/feedback/Badge.svelte';
  import LineChart from '../../lib/components/charts/LineChart.svelte';
  import DataTable, { type Column } from '../../lib/components/tables/DataTable.svelte';
  import { getServerHealth, listServerChecks } from '../../lib/api/server';
  import { filters, toQuery } from '../../lib/stores/filters';
  import { ApiException } from '../../lib/types/api';
  import type { PaginationMeta } from '../../lib/types/api';
  import type { ServerHealth } from '../../lib/types/models';
  import { num, ms, percent, dateTime } from '../../lib/utils/format';

  let health = $state<ServerHealth | null>(null);
  let rows = $state<Record<string, unknown>[]>([]);
  let pagination = $state<PaginationMeta | undefined>(undefined);
  let loading = $state(true);
  let error = $state<string | null>(null);
  let lastUpdated = $state<string | null>(null);
  let page = $state(1);
  let statusFilter = $state('');

  const columns: Column[] = [
    { key: 'domain', label: 'Domain' },
    { key: 'checked_at', label: 'Waktu', format: (v) => dateTime(v as string) },
    { key: 'status', label: 'Status', align: 'center', badge: true },
    { key: 'response_time_ms', label: 'Response', align: 'right', format: (v) => ms(v as number) },
    { key: 'http_status', label: 'HTTP', align: 'right' },
    { key: 'region', label: 'Region' },
    { key: 'error_message', label: 'Error' },
  ];

  async function load() {
    loading = true;
    error = null;
    try {
      const q = toQuery($filters);
      const [h, c] = await Promise.all([
        getServerHealth(q),
        listServerChecks({ ...q, page, per_page: 20, status: statusFilter || undefined }),
      ]);
      health = h;
      rows = c.items;
      pagination = c.pagination;
      lastUpdated = new Date().toISOString();
    } catch (err) {
      error = err instanceof ApiException ? err.message : 'Gagal memuat server health.';
    } finally {
      loading = false;
    }
  }

  function onPage(p: number) { page = p; load(); }
  function applyFilter() { page = 1; load(); }

  const tsLabels = $derived(health?.timeseries.map((t) => t.date) ?? []);
  const uptimeSeries = $derived(
    health ? [{ label: 'Uptime %', color: '#16A34A', points: health.timeseries.map((t) => t.uptime_pct) }] : []
  );
  const respSeries = $derived(
    health ? [{ label: 'Avg Response (ms)', color: '#2563EB', points: health.timeseries.map((t) => t.avg_response_ms) }] : []
  );

  $effect(() => { load(); });
</script>

<PageHeader
  title="Server Health"
  description="Monitoring uptime server dan response time per domain berdasarkan health check periodik."
  {lastUpdated}
  {loading}
  onRefresh={load} />

<GlobalFilter onApply={applyFilter} />

{#if health}
  <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <StatCard
      label="Uptime"
      value={percent(health.overall.uptime_pct, 2)}
      status={health.overall.status}
      sub={`${num(health.overall.up_checks)} / ${num(health.overall.total_checks)} checks`}
      icon="🟢" />
    <StatCard label="Avg Response" value={ms(health.overall.avg_response_ms)} sub={`p95: ${ms(health.overall.p95_response_ms)}`} icon="⚡" />
    <StatCard label="Max Response" value={ms(health.overall.max_response_ms)} icon="🐢" />
    <StatCard
      label="Incidents"
      value={num(health.overall.incidents)}
      status={health.overall.incidents > 0 ? 'warning' : 'healthy'}
      icon="🚨" />
  </div>

  <!-- Per-domain status -->
  <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
    {#each health.domains as d}
      <div class="surface-card">
        <div class="card-body gap-2 p-5">
          <div class="flex items-center justify-between">
            <h2 class="card-title text-base">{d.domain}</h2>
            <Badge status={d.current_status} />
          </div>
          <dl class="space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-base-content/60">Uptime</dt><dd class="num font-semibold">{percent(d.uptime_pct, 2)}</dd></div>
            <div class="flex justify-between"><dt class="text-base-content/60">Avg Response</dt><dd class="num">{ms(d.avg_response_ms)}</dd></div>
            <div class="flex justify-between"><dt class="text-base-content/60">Last Check</dt><dd class="text-xs">{dateTime(d.last_checked_at)}</dd></div>
          </dl>
          <Badge status={d.status} />
        </div>
      </div>
    {/each}
  </div>

  <!-- Charts -->
  <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
    <div class="surface-card">
      <div class="card-body p-5">
        <h2 class="card-title text-base">Tren Uptime (%)</h2>
        <LineChart labels={tsLabels} series={uptimeSeries} valueFormat={(v) => v.toFixed(2) + '%'} />
      </div>
    </div>
    <div class="surface-card">
      <div class="card-body p-5">
        <h2 class="card-title text-base">Tren Response Time (ms)</h2>
        <LineChart labels={tsLabels} series={respSeries} valueFormat={(v) => Math.round(v) + 'ms'} />
      </div>
    </div>
  </div>
{/if}

<div class="mb-3 mt-4 flex items-center gap-2">
  <h2 class="text-sm font-semibold">Log Health Check</h2>
  <select class="select select-bordered select-xs" bind:value={statusFilter} onchange={applyFilter}>
    <option value="">Semua status</option>
    <option value="up">Up</option>
    <option value="down">Down (incident)</option>
  </select>
</div>

<DataTable {columns} {rows} {loading} {error} {pagination} {onPage} onRetry={load} />
