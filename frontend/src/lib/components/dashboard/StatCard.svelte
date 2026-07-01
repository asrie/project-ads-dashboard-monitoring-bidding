<script lang="ts">
  let {
    label,
    value,
    sub = '',
    status = '',
    icon = '',
  }: {
    label: string;
    value: string;
    sub?: string;
    status?: string;
    icon?: string;
  } = $props();

  const accent: Record<string, string> = {
    healthy: 'text-success',
    warning: 'text-warning',
    critical: 'text-error',
  };
</script>

<div class="surface-card">
  <div class="card-body gap-1 p-5">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium uppercase tracking-wide text-base-content/50">{label}</span>
      {#if icon}<span class="text-lg opacity-70">{icon}</span>{/if}
    </div>
    <span class="text-2xl font-bold {status ? (accent[status] ?? 'text-base-content') : 'text-base-content'}">
      {value}
    </span>
    {#if sub}
      <span class="text-xs text-base-content/50">{sub}</span>
    {/if}
  </div>
</div>
