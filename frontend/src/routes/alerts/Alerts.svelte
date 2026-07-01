<script lang="ts">
  import PageHeader from '../../lib/components/feedback/PageHeader.svelte';
  import GlobalFilter from '../../lib/components/forms/GlobalFilter.svelte';
  import DataTable, { type Column } from '../../lib/components/tables/DataTable.svelte';
  import Badge from '../../lib/components/feedback/Badge.svelte';
  import { listAlerts, acknowledgeAlert, listInsights } from '../../lib/api/alerts';
  import { filters, toQuery } from '../../lib/stores/filters';
  import { ApiException } from '../../lib/types/api';
  import type { PaginationMeta } from '../../lib/types/api';
  import type { AlertRow, Insight } from '../../lib/types/models';
  import { user } from '../../lib/stores/auth';
  import { pushToast } from '../../lib/stores/ui';
  import { dateTime, num } from '../../lib/utils/format';

  let rows = $state<AlertRow[]>([]);
  let insights = $state<Insight[]>([]);
  let pagination = $state<PaginationMeta | undefined>(undefined);
  let loading = $state(true);
  let error = $state<string | null>(null);
  let lastUpdated = $state<string | null>(null);
  let page = $state(1);
  let severityFilter = $state('');
  let statusFilter = $state('');
  let acking = $state<string | null>(null);

  const canAck = $derived($user?.role !== 'viewer');

  const columns: Column[] = [
    { key: 'severity', label: 'Severity', align: 'center', badge: true },
    { key: 'category', label: 'Kategori' },
    { key: 'metric', label: 'Metric' },
    { key: 'message', label: 'Pesan' },
    { key: 'current_value', label: 'Nilai', align: 'right', format: (v) => (v == null ? '–' : num(v as number)) },
    { key: 'threshold_value', label: 'Threshold', align: 'right', format: (v) => (v == null ? '–' : num(v as number)) },
    { key: 'status', label: 'Status', align: 'center', badge: true },
    { key: 'triggered_at', label: 'Waktu', format: (v) => dateTime(v as string) },
  ];

  async function load() {
    loading = true;
    error = null;
    try {
      const q = toQuery($filters);
      const [a, i] = await Promise.all([
        listAlerts({ ...q, page, per_page: 20, severity: severityFilter || undefined, status: statusFilter || undefined }),
        listInsights(q),
      ]);
      rows = a.items;
      pagination = a.pagination;
      insights = i;
      lastUpdated = new Date().toISOString();
    } catch (err) {
      error = err instanceof ApiException ? err.message : 'Gagal memuat alerts.';
    } finally {
      loading = false;
    }
  }

  async function ack(id: string) {
    acking = id;
    try {
      const updated = await acknowledgeAlert(id);
      rows = rows.map((r) => (r.id === id ? updated : r));
      pushToast('Alert di-acknowledge.', 'success');
    } catch (err) {
      pushToast(err instanceof ApiException ? err.message : 'Gagal acknowledge.', 'error');
    } finally {
      acking = null;
    }
  }

  function onPage(p: number) { page = p; load(); }
  function applyFilter() { page = 1; load(); }

  $effect(() => { load(); });
</script>

<PageHeader
  title="Alerts & Insights"
  description="Alert aktif berdasarkan threshold dan insight optimasi otomatis."
  {lastUpdated}
  {loading}
  onRefresh={load} />

<GlobalFilter onApply={applyFilter} />

<div class="mb-3 flex flex-wrap items-center gap-2">
  <select class="select select-bordered select-xs" bind:value={severityFilter} onchange={applyFilter}>
    <option value="">Semua severity</option>
    <option value="critical">Critical</option>
    <option value="high">High</option>
    <option value="medium">Medium</option>
    <option value="low">Low</option>
  </select>
  <select class="select select-bordered select-xs" bind:value={statusFilter} onchange={applyFilter}>
    <option value="">Semua status</option>
    <option value="open">Open</option>
    <option value="acknowledged">Acknowledged</option>
    <option value="resolved">Resolved</option>
  </select>
</div>

<DataTable {columns} rows={rows as unknown as Record<string, unknown>[]} {loading} {error} {pagination} {onPage} onRetry={load}>
  {#snippet actions(row)}
    {#if canAck && row.status === 'open'}
      <button class="btn btn-xs btn-primary" disabled={acking === row.id} onclick={() => ack(String(row.id))}>
        {#if acking === row.id}<span class="loading loading-spinner loading-xs"></span>{/if}
        Acknowledge
      </button>
    {:else if row.status === 'open'}
      <span class="text-xs text-base-content/40">read-only</span>
    {:else}
      <span class="text-xs text-base-content/40">—</span>
    {/if}
  {/snippet}
</DataTable>

<!-- Insights -->
<div class="mt-6">
  <h2 class="mb-3 text-lg font-semibold">Insights</h2>
  {#if insights.length === 0}
    <div class="surface-card"><div class="card-body p-6 text-center text-sm text-base-content/50">Belum ada insight untuk periode ini.</div></div>
  {:else}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
      {#each insights as ins}
        <div class="surface-card">
          <div class="card-body gap-2 p-5">
            <div class="flex items-start justify-between gap-2">
              <h3 class="font-semibold text-base-content">{ins.title}</h3>
              <Badge status={ins.impact} label={ins.impact + ' impact'} />
            </div>
            <p class="text-sm text-base-content/70">{ins.description}</p>
            <div class="flex flex-wrap gap-2 text-xs text-base-content/50">
              <span class="badge badge-outline badge-sm">{ins.type}</span>
              {#if ins.related_metric}<span class="badge badge-outline badge-sm">{ins.related_metric}</span>{/if}
              {#if ins.domain}<span>{ins.domain}</span>{/if}
            </div>
          </div>
        </div>
      {/each}
    </div>
  {/if}
</div>
