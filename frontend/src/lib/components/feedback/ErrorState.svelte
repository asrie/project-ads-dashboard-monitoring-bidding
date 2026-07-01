<script lang="ts">
  let {
    message = 'Terjadi kesalahan saat memuat data.',
    onRetry,
  }: { message?: string; onRetry?: () => void } = $props();
</script>

<div class="flex flex-col items-center justify-center gap-3 py-12 text-center">
  <div class="rounded-full bg-error/10 p-4 text-3xl">⚠️</div>
  <h3 class="text-base font-semibold text-error">Gagal memuat</h3>
  <p class="max-w-md text-sm text-base-content/60">{message}</p>
  {#if onRetry}
    <button class="btn btn-sm btn-outline" onclick={onRetry}>Coba lagi</button>
  {/if}
</div>
