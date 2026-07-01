<script lang="ts">
  let {
    title = 'Tidak ada data',
    description = 'Tidak ada data untuk filter yang dipilih.',
  }: { title?: string; description?: string } = $props();
</script>

<div class="flex flex-col items-center justify-center gap-2 py-12 text-center">
  <div class="rounded-full bg-base-200 p-4 text-3xl">📭</div>
  <h3 class="text-base font-semibold text-base-content">{title}</h3>
  <p class="max-w-sm text-sm text-base-content/60">{description}</p>
</div>
