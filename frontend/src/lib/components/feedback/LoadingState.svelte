<script lang="ts">
  let { label = 'Memuat data…' }: { label?: string } = $props();
</script>

<div class="flex flex-col items-center justify-center gap-3 py-12 text-base-content/60">
  <span class="loading loading-spinner loading-lg text-primary"></span>
  <p class="text-sm">{label}</p>
</div>
