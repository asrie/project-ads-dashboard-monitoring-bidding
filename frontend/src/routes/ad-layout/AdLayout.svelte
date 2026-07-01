<script lang="ts">
  import PageHeader from '../../lib/components/feedback/PageHeader.svelte';
  import LoadingState from '../../lib/components/feedback/LoadingState.svelte';
  import { listDomains } from '../../lib/api/domains';
  import { capturePreview, listPreviews, getPreview } from '../../lib/api/preview';
  import { ApiException } from '../../lib/types/api';
  import type { DomainRef, PagePreviewDetail, PagePreviewSummary, PreviewSlot } from '../../lib/types/models';
  import { user } from '../../lib/stores/auth';
  import { pushToast } from '../../lib/stores/ui';
  import { currency, num, percent, dateTime } from '../../lib/utils/format';

  const FRAME_W = 380; // px the screenshot is rendered at inside the phone frame

  let domains = $state<DomainRef[]>([]);
  let selectedDomain = $state('');
  let capturing = $state(false);
  let loading = $state(false);
  let preview = $state<PagePreviewDetail | null>(null);
  let history = $state<PagePreviewSummary[]>([]);
  let selectedSlot = $state<number | null>(null);

  const canCapture = $derived($user?.role !== 'viewer');
  const scale = $derived(preview ? FRAME_W / (preview.page_width || preview.viewport_css_width || 390) : 1);

  async function init() {
    try {
      domains = await listDomains();
      if (domains.length && !selectedDomain) selectedDomain = domains[0].id;
    } catch (e) {
      pushToast(e instanceof ApiException ? e.message : 'Gagal memuat domain.', 'error');
    }
  }

  async function loadHistory() {
    if (!selectedDomain) return;
    try {
      const res = await listPreviews({ domain_id: selectedDomain, per_page: 8 });
      history = res.items;
    } catch {
      history = [];
    }
  }

  async function onSelectDomain() {
    preview = null;
    selectedSlot = null;
    await loadHistory();
  }

  async function capture() {
    if (!selectedDomain) return;
    capturing = true;
    selectedSlot = null;
    try {
      preview = await capturePreview(selectedDomain);
      pushToast(`Capture selesai — ${preview.slot_count} slot terdeteksi.`, 'success');
      await loadHistory();
    } catch (e) {
      pushToast(e instanceof ApiException ? e.message : 'Capture gagal.', 'error');
    } finally {
      capturing = false;
    }
  }

  async function openPreview(id: string) {
    loading = true;
    selectedSlot = null;
    try {
      preview = await getPreview(id);
    } catch (e) {
      pushToast(e instanceof ApiException ? e.message : 'Gagal memuat preview.', 'error');
    } finally {
      loading = false;
    }
  }

  function boxStyle(slot: PreviewSlot): string {
    if (!slot.rect) return 'display:none';
    const r = slot.rect;
    return `left:${r.x * scale}px;top:${r.y * scale}px;width:${r.w * scale}px;height:${r.h * scale}px;`;
  }

  function slotClass(slot: PreviewSlot, idx: number): string {
    const base = slot.type === 'header_bidding'
      ? 'border-secondary bg-secondary/20'
      : 'border-warning bg-warning/20';
    const border = slot.matched ? 'border-solid' : 'border-dashed';
    const active = selectedSlot === idx ? 'ring-2 ring-primary z-10' : '';
    return `${base} ${border} ${active}`;
  }

  $effect(() => { init(); });
  $effect(() => { if (selectedDomain) loadHistory(); });
</script>

<PageHeader
  title="Ad Layout Preview"
  description="Snapshot mobile situs + peta posisi slot iklan (header-bidding vs direct), di-render server-side." />

<!-- Controls -->
<div class="surface-card mb-5">
  <div class="card-body flex flex-row flex-wrap items-end gap-3 p-4">
    <label class="form-control min-w-60 flex-1">
      <span class="label-text mb-1 text-xs text-base-content/60">Domain (homepage, mobile)</span>
      <select class="select select-bordered select-sm" bind:value={selectedDomain} onchange={onSelectDomain}>
        {#each domains as d}<option value={d.id}>{d.name} — {d.url}</option>{/each}
      </select>
    </label>
    <button class="btn btn-primary btn-sm gap-2" onclick={capture} disabled={!canCapture || capturing || !selectedDomain}>
      {#if capturing}<span class="loading loading-spinner loading-xs"></span>{/if}
      {capturing ? 'Merender…' : 'Capture Preview'}
    </button>
    {#if !canCapture}<span class="text-xs text-base-content/50">Role read-only — tak bisa capture.</span>{/if}
  </div>
</div>

{#if capturing}
  <LoadingState label="Merender halaman mobile + mendeteksi slot (bisa ~30 detik)" />
{:else if loading}
  <LoadingState />
{:else if preview}
  <div class="grid grid-cols-1 gap-5 lg:grid-cols-[auto_1fr]">
    <!-- Phone frame -->
    <div>
      <div class="mx-auto rounded-[2rem] border-8 border-neutral bg-neutral p-1 shadow-lg" style="width:{FRAME_W + 18}px">
        <div class="relative overflow-y-auto rounded-[1.5rem] bg-base-100" style="width:{FRAME_W}px;max-height:680px">
          {#if preview.image}
            <img src={preview.image} alt="Snapshot {preview.url}" style="width:{FRAME_W}px;display:block" />
            <!-- Header region -->
            {#if preview.header}
              <div class="pointer-events-none absolute border border-dashed border-info/70 bg-info/10"
                style="left:{preview.header.x * scale}px;top:{preview.header.y * scale}px;width:{preview.header.w * scale}px;height:{preview.header.h * scale}px">
                <span class="absolute left-0 top-0 bg-info px-1 text-[9px] text-info-content">HEADER</span>
              </div>
            {/if}
            <!-- Slot overlays -->
            {#each preview.slots as slot, i}
              {#if slot.rect}
                <button
                  class="absolute border-2 {slotClass(slot, i)} cursor-pointer"
                  style={boxStyle(slot)}
                  onclick={() => (selectedSlot = i)}
                  title={slot.ad_unit_path ?? slot.element_id ?? 'slot'}
                  aria-label="Slot {i + 1}">
                  <span class="absolute -top-0.5 left-0 bg-base-content/80 px-1 text-[9px] leading-tight text-base-100">
                    {slot.type === 'header_bidding' ? 'HB' : 'DIR'}{slot.matched ? '✓' : ''}
                  </span>
                </button>
              {/if}
            {/each}
          {:else}
            <div class="p-8 text-center text-sm text-base-content/50">Screenshot tidak tersedia.</div>
          {/if}
        </div>
      </div>
      <p class="mt-2 text-center text-xs text-base-content/50">{dateTime(preview.captured_at)} · {preview.slot_count} slot</p>
    </div>

    <!-- Detail + legend -->
    <div class="space-y-4">
      <!-- Legend -->
      <div class="surface-card"><div class="card-body gap-2 p-4">
        <h2 class="card-title text-sm">Legend</h2>
        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-base-content/70">
          <span><span class="mr-1 inline-block h-3 w-3 rounded-sm border-2 border-solid border-secondary bg-secondary/20"></span>Header bidding</span>
          <span><span class="mr-1 inline-block h-3 w-3 rounded-sm border-2 border-solid border-warning bg-warning/20"></span>Direct / AdX</span>
          <span><span class="mr-1 inline-block h-3 w-3 rounded-sm border-2 border-dashed border-base-content/50"></span>Belum termap ke ad_slots</span>
          <span><span class="mr-1 inline-block h-3 w-3 rounded-sm border border-dashed border-info"></span>Header region</span>
        </div>
      </div></div>

      <!-- Selected slot -->
      <div class="surface-card"><div class="card-body p-5">
        <h2 class="card-title text-base">Detail Slot</h2>
        {#if selectedSlot === null || !preview.slots[selectedSlot]}
          <p class="text-sm text-base-content/50">Klik kotak slot di preview untuk melihat detail.</p>
        {:else}
          {@const s = preview.slots[selectedSlot]}
          <div class="space-y-2 text-sm">
            <div class="flex items-center gap-2">
              <span class="badge badge-sm {s.type === 'header_bidding' ? 'badge-secondary' : 'badge-warning'}">
                {s.type === 'header_bidding' ? 'Header bidding' : 'Direct / AdX'}
              </span>
              {#if s.matched}<span class="badge badge-sm badge-success">Termap: {s.slot_name}</span>
              {:else}<span class="badge badge-sm badge-outline">Belum termap</span>{/if}
            </div>
            <div class="break-all"><span class="text-base-content/60">Ad unit:</span> {s.ad_unit_path ?? '–'}</div>
            <div><span class="text-base-content/60">Ukuran:</span> {(s.sizes ?? []).map((z) => Array.isArray(z) ? z.join('×') : z).join(', ') || '–'}</div>
            {#if s.metrics}
              <div class="mt-2 grid grid-cols-2 gap-2">
                <div class="rounded bg-base-200 p-2"><p class="text-xs text-base-content/50">eCPM</p><p class="font-semibold">{currency(s.metrics.ecpm)}</p></div>
                <div class="rounded bg-base-200 p-2"><p class="text-xs text-base-content/50">Fill rate</p><p class="font-semibold">{percent(s.metrics.fill_rate)}</p></div>
                <div class="rounded bg-base-200 p-2"><p class="text-xs text-base-content/50">Revenue</p><p class="font-semibold">{currency(s.metrics.revenue)}</p></div>
                <div class="rounded bg-base-200 p-2"><p class="text-xs text-base-content/50">Impressions</p><p class="font-semibold">{num(s.metrics.impressions)}</p></div>
              </div>
            {:else}
              <p class="text-xs text-base-content/50">Tidak ada metrik (slot belum termap ke ad_slots — selaraskan ad_unit_path).</p>
            {/if}
          </div>
        {/if}
      </div></div>
    </div>
  </div>
{:else}
  <div class="surface-card"><div class="card-body p-8 text-center text-sm text-base-content/50">
    Pilih domain lalu klik <b>Capture Preview</b>, atau buka riwayat di bawah.
  </div></div>
{/if}

<!-- History -->
<div class="mt-6">
  <h2 class="mb-3 text-lg font-semibold">Riwayat Capture</h2>
  <div class="surface-card"><div class="card-body p-0">
    {#if history.length === 0}
      <p class="p-6 text-center text-sm text-base-content/50">Belum ada capture untuk domain ini.</p>
    {:else}
      <div class="overflow-x-auto">
        <table class="table table-sm">
          <thead class="bg-base-200"><tr><th>Waktu</th><th>URL</th><th class="text-center">Status</th><th class="text-right">Slot</th><th></th></tr></thead>
          <tbody>
            {#each history as h}
              <tr class="hover">
                <td>{dateTime(h.captured_at)}</td>
                <td class="max-w-xs truncate">{h.url}</td>
                <td class="text-center"><span class="badge badge-sm {h.status === 'completed' ? 'badge-success' : 'badge-error'}">{h.status}</span></td>
                <td class="num">{h.slot_count}</td>
                <td class="text-right">
                  {#if h.status === 'completed'}<button class="btn btn-xs btn-ghost" onclick={() => openPreview(h.id)}>Lihat</button>{/if}
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    {/if}
  </div></div>
</div>
