<script lang="ts">
  import PageHeader from '../../lib/components/feedback/PageHeader.svelte';
  import GlobalFilter from '../../lib/components/forms/GlobalFilter.svelte';
  import DataTable, { type Column } from '../../lib/components/tables/DataTable.svelte';
  import { listSlots } from '../../lib/api/slots';
  import { filters, toQuery } from '../../lib/stores/filters';
  import { ApiException } from '../../lib/types/api';
  import type { PaginationMeta } from '../../lib/types/api';
  import type { SlotRow } from '../../lib/types/models';
  import { currency, num, percent } from '../../lib/utils/format';

  let rows = $state<SlotRow[]>([]);
  let pagination = $state<PaginationMeta | undefined>(undefined);
  let loading = $state(true);
  let error = $state<string | null>(null);
  let lastUpdated = $state<string | null>(null);

  let page = $state(1);
  let sortBy = $state('revenue');
  let sortDir = $state<'asc' | 'desc'>('desc');
  let search = $state('');

  const columns: Column[] = [
    { key: 'name', label: 'Slot', sortable: true },
    { key: 'domain', label: 'Domain' },
    { key: 'device', label: 'Device', align: 'center' },
    { key: 'revenue', label: 'Revenue', align: 'right', sortable: true, format: (v) => currency(v as number) },
    { key: 'impressions', label: 'Impressions', align: 'right', sortable: true, format: (v) => num(v as number) },
    { key: 'ad_requests', label: 'Ad Req', align: 'right', sortable: true, format: (v) => num(v as number) },
    { key: 'fill_rate', label: 'Fill Rate', align: 'right', sortable: true, format: (v) => percent(v as number) },
    { key: 'ecpm', label: 'eCPM', align: 'right', sortable: true, format: (v) => currency(v as number) },
    { key: 'viewability', label: 'Viewability', align: 'right', format: (v) => percent(v as number) },
  ];

  async function load() {
    loading = true;
    error = null;
    try {
      const res = await listSlots({ ...toQuery($filters), page, per_page: 20, sort_by: sortBy, sort_dir: sortDir, search });
      rows = res.items;
      pagination = res.pagination;
      lastUpdated = new Date().toISOString();
    } catch (err) {
      error = err instanceof ApiException ? err.message : 'Gagal memuat slot.';
    } finally {
      loading = false;
    }
  }

  function onSort(key: string) {
    if (sortBy === key) sortDir = sortDir === 'asc' ? 'desc' : 'asc';
    else { sortBy = key; sortDir = 'desc'; }
    page = 1;
    load();
  }

  function onPage(p: number) { page = p; load(); }
  function applyFilter() { page = 1; load(); }

  $effect(() => { load(); });
</script>

<PageHeader
  title="Slot Performance"
  description="Performa setiap slot iklan: revenue, fill rate, eCPM, dan viewability."
  {lastUpdated}
  {loading}
  onRefresh={load} />

<GlobalFilter onApply={applyFilter} />

<div class="mb-3">
  <input
    type="text"
    class="input input-bordered input-sm w-full max-w-xs"
    placeholder="Cari nama slot…"
    bind:value={search}
    onkeydown={(e) => e.key === 'Enter' && applyFilter()} />
</div>

<DataTable {columns} rows={rows as unknown as Record<string, unknown>[]} {loading} {error} {pagination} {sortBy} {sortDir} {onSort} {onPage} onRetry={load} />
