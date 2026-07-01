<script lang="ts">
  import { dateTime } from '../../utils/format';

  let {
    title,
    description = '',
    lastUpdated = null,
    loading = false,
    onRefresh,
  }: {
    title: string;
    description?: string;
    lastUpdated?: string | null;
    loading?: boolean;
    onRefresh?: () => void;
  } = $props();
</script>

<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
  <div>
    <h1 class="text-2xl font-bold text-base-content">{title}</h1>
    {#if description}
      <p class="mt-1 text-sm text-base-content/60">{description}</p>
    {/if}
  </div>
  <div class="flex items-center gap-3">
    {#if lastUpdated}
      <span class="hidden text-xs text-base-content/50 sm:inline">
        Terakhir diperbarui: {dateTime(lastUpdated)}
      </span>
    {/if}
    {#if onRefresh}
      <button class="btn btn-sm btn-outline gap-2" onclick={onRefresh} disabled={loading}>
        {#if loading}
          <span class="loading loading-spinner loading-xs"></span>
        {/if}
        Refresh
      </button>
    {/if}
  </div>
</div>
