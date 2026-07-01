<script lang="ts">
  import PageHeader from '../../lib/components/feedback/PageHeader.svelte';
  import GlobalFilter from '../../lib/components/forms/GlobalFilter.svelte';
  import StatCard from '../../lib/components/dashboard/StatCard.svelte';
  import BarChart from '../../lib/components/charts/BarChart.svelte';
  import DataTable, { type Column } from '../../lib/components/tables/DataTable.svelte';
  import { listNetworkAds, getHeavyRequests } from '../../lib/api/networkAds';
  import { filters, toQuery } from '../../lib/stores/filters';
  import { ApiException } from '../../lib/types/api';
  import type { PaginationMeta } from '../../lib/types/api';
  import { num, ms, bytes } from '../../lib/utils/format';

  let summary = $state<Record<string, number> | null>(null);
  let byVendor = $state<Array<Record<string, unknown>>>([]);
  let rows = $state<Record<string, unknown>[]>([]);
  let pagination = $state<PaginationMeta | undefined>(undefined);
  let loading = $state(true);
  let error = $state<string | null>(null);
  let lastUpdated = $state<string | null>(null);
  let page = $state(1);
  let typeFilter = $state('');
  let thirdParty = $state('');

  const columns: Column[] = [
    { key: 'resource_url', label: 'Resource', format: (v) => { const s = String(v ?? ''); return s.length > 48 ? s.slice(0, 47) + '…' : s; } },
    { key: 'vendor', label: 'Vendor' },
    { key: 'type', label: 'Tipe', align: 'center' },
    { key: 'size_bytes', label: 'Ukuran', align: 'right', format: (v) => bytes(v as number) },
    { key: 'duration_ms', label: 'Durasi', align: 'right', format: (v) => ms(v as number) },
    { key: 'is_third_party', label: '3rd Party', align: 'center', format: (v) => (v ? 'Ya' : 'Tidak') },
    { key: 'is_blocking', label: 'Blocking', align: 'center', format: (v) => (v ? 'Ya' : 'Tidak') },
  ];

  async function load() {
    loading = true;
    error = null;
    try {
      const q = toQuery($filters);
      const [heavy, list] = await Promise.all([
        getHeavyRequests(q),
        listNetworkAds({
          ...q,
          page,
          per_page: 20,
          type: typeFilter || undefined,
          third_party: thirdParty === '' ? undefined : thirdParty,
        }),
      ]);
      summary = heavy.summary;
      byVendor = heavy.by_vendor;
      rows = list.items;
      pagination = list.pagination;
      lastUpdated = new Date().toISOString();
    } catch (err) {
      error = err instanceof ApiException ? err.message : 'Gagal memuat network ads.';
    } finally {
      loading = false;
    }
  }

  function onPage(p: number) { page = p; load(); }
  function applyFilter() { page = 1; load(); }

  const vendorLabels = $derived(byVendor.map((v) => String(v.vendor ?? '')));
  const vendorValues = $derived(byVendor.map((v) => Number(v.total_bytes ?? 0) / 1024));

  $effect(() => { load(); });
</script>

<PageHeader
  title="Network Ads"
  description="Network request dari script ads dan third-party JavaScript yang berat."
  {lastUpdated}
  {loading}
  onRefresh={load} />

<GlobalFilter onApply={applyFilter} />

{#if summary}
  <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <StatCard label="Total Request" value={num(summary.total_requests)} icon="🌐" />
    <StatCard label="Total Transfer" value={bytes(summary.total_bytes)} icon="📦" />
    <StatCard label="Blocking" value={num(summary.blocking_requests)} status={summary.blocking_requests > 0 ? 'warning' : 'healthy'} icon="🚧" />
    <StatCard label="Third Party" value={num(summary.third_party_requests)} icon="🔗" />
  </div>

  <div class="surface-card mt-4">
    <div class="card-body p-5">
      <h2 class="card-title text-base">Top Vendor berdasarkan Transfer (KB)</h2>
      <BarChart labels={vendorLabels} values={vendorValues} color="#1E3A8A" valueFormat={(v) => Math.round(v) + 'KB'} />
    </div>
  </div>
{/if}

<div class="mb-3 mt-4 flex flex-wrap items-center gap-2">
  <h2 class="text-sm font-semibold">Resource Terberat</h2>
  <select class="select select-bordered select-xs" bind:value={typeFilter} onchange={applyFilter}>
    <option value="">Semua tipe</option>
    <option value="script">Script</option>
    <option value="xhr">XHR</option>
    <option value="img">Image</option>
    <option value="css">CSS</option>
    <option value="font">Font</option>
  </select>
  <select class="select select-bordered select-xs" bind:value={thirdParty} onchange={applyFilter}>
    <option value="">Semua sumber</option>
    <option value="true">Third party</option>
    <option value="false">First party</option>
  </select>
</div>

<DataTable {columns} {rows} {loading} {error} {pagination} {onPage} onRetry={load} />
