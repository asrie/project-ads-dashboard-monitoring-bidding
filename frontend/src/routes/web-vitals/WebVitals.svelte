<script lang="ts">
  import PageHeader from '../../lib/components/feedback/PageHeader.svelte';
  import GlobalFilter from '../../lib/components/forms/GlobalFilter.svelte';
  import Badge from '../../lib/components/feedback/Badge.svelte';
  import LineChart from '../../lib/components/charts/LineChart.svelte';
  import DataTable, { type Column } from '../../lib/components/tables/DataTable.svelte';
  import { getWebVitalsSummary, listVitalsPages } from '../../lib/api/webVitals';
  import { filters, toQuery } from '../../lib/stores/filters';
  import { ApiException } from '../../lib/types/api';
  import type { PaginationMeta } from '../../lib/types/api';
  import type { WebVitalsSummary } from '../../lib/types/models';
  import { num } from '../../lib/utils/format';

  let summary = $state<WebVitalsSummary | null>(null);
  let rows = $state<Record<string, unknown>[]>([]);
  let pagination = $state<PaginationMeta | undefined>(undefined);
  let loading = $state(true);
  let error = $state<string | null>(null);
  let lastUpdated = $state<string | null>(null);
  let page = $state(1);
  let search = $state('');

  const metricKeys = ['lcp', 'inp', 'cls', 'fcp', 'ttfb', 'tbt'];

  const columns: Column[] = [
    { key: 'path', label: 'Halaman' },
    { key: 'samples', label: 'Samples', align: 'right', format: (v) => num(v as number) },
    { key: 'lcp', label: 'LCP', align: 'right', format: (v) => num(v as number) },
    { key: 'lcp_status', label: 'LCP Rating', align: 'center', badge: true },
    { key: 'inp', label: 'INP', align: 'right', format: (v) => num(v as number) },
    { key: 'cls', label: 'CLS', align: 'right' },
    { key: 'fcp', label: 'FCP', align: 'right', format: (v) => num(v as number) },
    { key: 'ttfb', label: 'TTFB', align: 'right', format: (v) => num(v as number) },
    { key: 'tbt', label: 'TBT', align: 'right', format: (v) => num(v as number) },
  ];

  async function load() {
    loading = true;
    error = null;
    try {
      const q = toQuery($filters);
      const [s, p] = await Promise.all([
        getWebVitalsSummary(q),
        listVitalsPages({ ...q, page, per_page: 20, search }),
      ]);
      summary = s;
      rows = p.items;
      pagination = p.pagination;
      lastUpdated = new Date().toISOString();
    } catch (err) {
      error = err instanceof ApiException ? err.message : 'Gagal memuat Web Vitals.';
    } finally {
      loading = false;
    }
  }

  function onPage(p: number) { page = p; load(); }
  function applyFilter() { page = 1; load(); }

  const chartLabels = $derived(summary?.timeseries.map((t) => String(t.date)) ?? []);
  const chartSeries = $derived(
    summary
      ? [
          { label: 'LCP', color: '#1E3A8A', points: summary.timeseries.map((t) => Number(t.lcp)) },
          { label: 'FCP', color: '#38BDF8', points: summary.timeseries.map((t) => Number(t.fcp)) },
        ]
      : []
  );
</script>

<PageHeader
  title="Web Core Vitals"
  description="LCP, INP, CLS, FCP, TTFB, TBT — performa pengalaman pengguna website."
  {lastUpdated}
  {loading}
  onRefresh={load} />

<GlobalFilter onApply={applyFilter} />

{#if summary}
  <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
    {#each metricKeys as key}
      {@const m = summary.metrics[key]}
      <div class="surface-card">
        <div class="card-body items-center gap-1 p-4 text-center">
          <span class="text-xs uppercase text-base-content/50">{key}</span>
          <span class="text-xl font-bold text-base-content">{num(m?.value ?? 0)}{m?.unit ?? ''}</span>
          {#if m}<Badge status={m.status} />{/if}
        </div>
      </div>
    {/each}
  </div>

  <div class="surface-card mt-4">
    <div class="card-body p-5">
      <h2 class="card-title text-base">Tren LCP & FCP (ms)</h2>
      <LineChart labels={chartLabels} series={chartSeries} valueFormat={(v) => Math.round(v) + 'ms'} />
    </div>
  </div>
{/if}

<div class="mb-3 mt-4">
  <input
    type="text"
    class="input input-bordered input-sm w-full max-w-xs"
    placeholder="Cari path halaman…"
    bind:value={search}
    onkeydown={(e) => e.key === 'Enter' && applyFilter()} />
</div>

<DataTable {columns} {rows} {loading} {error} {pagination} {onPage} onRetry={load} />
