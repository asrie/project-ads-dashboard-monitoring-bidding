<script lang="ts">
  import { filters } from '../../stores/filters';
  import type { Device } from '../../types/models';

  let { onApply }: { onApply?: () => void } = $props();

  // Local copies so changes only take effect on "Terapkan".
  let dateFrom = $state($filters.date_from ?? '');
  let dateTo = $state($filters.date_to ?? '');
  let device = $state<Device | ''>($filters.device ?? '');
  let domainId = $state($filters.domain_id ?? '');

  function apply() {
    filters.set({
      date_from: dateFrom || null,
      date_to: dateTo || null,
      device: (device || null) as Device | null,
      domain_id: domainId || null,
    });
    onApply?.();
  }

  function reset() {
    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - 29);
    dateFrom = from.toISOString().slice(0, 10);
    dateTo = to.toISOString().slice(0, 10);
    device = '';
    domainId = '';
    apply();
  }
</script>

<div class="surface-card mb-5">
  <div class="card-body flex flex-row flex-wrap items-end gap-3 p-4">
    <label class="form-control">
      <span class="label-text mb-1 text-xs text-base-content/60">Dari tanggal</span>
      <input type="date" class="input input-bordered input-sm" bind:value={dateFrom} max={dateTo} />
    </label>
    <label class="form-control">
      <span class="label-text mb-1 text-xs text-base-content/60">Sampai tanggal</span>
      <input type="date" class="input input-bordered input-sm" bind:value={dateTo} min={dateFrom} />
    </label>
    <label class="form-control">
      <span class="label-text mb-1 text-xs text-base-content/60">Device</span>
      <select class="select select-bordered select-sm" bind:value={device}>
        <option value="">Semua</option>
        <option value="desktop">Desktop</option>
        <option value="mobile">Mobile</option>
        <option value="tablet">Tablet</option>
      </select>
    </label>
    <label class="form-control min-w-48 flex-1">
      <span class="label-text mb-1 text-xs text-base-content/60">Domain ID (opsional)</span>
      <input type="text" class="input input-bordered input-sm" placeholder="UUID domain" bind:value={domainId} />
    </label>
    <div class="flex gap-2">
      <button class="btn btn-primary btn-sm" onclick={apply}>Terapkan</button>
      <button class="btn btn-ghost btn-sm" onclick={reset}>Reset</button>
    </div>
  </div>
</div>
