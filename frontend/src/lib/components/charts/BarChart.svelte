<script lang="ts">
  // Lightweight dependency-free SVG bar chart (single series).
  let {
    labels = [],
    values = [],
    color = '#2563EB',
    height = 240,
    valueFormat = (v: number) => String(Math.round(v)),
  }: {
    labels?: string[];
    values?: number[];
    color?: string;
    height?: number;
    valueFormat?: (v: number) => string;
  } = $props();

  const width = 720;
  const padding = { top: 16, right: 16, bottom: 40, left: 48 };
  const innerW = width - padding.left - padding.right;
  const innerH = $derived(height - padding.top - padding.bottom);

  const maxVal = $derived(values.length ? Math.max(...values, 0) || 1 : 1);
  const count = $derived(values.length || 1);
  const barW = $derived((innerW / count) * 0.6);
  const gap = $derived((innerW / count) * 0.4);

  function x(i: number): number {
    return padding.left + i * (barW + gap) + gap / 2;
  }
  function barHeight(v: number): number {
    return (v / maxVal) * innerH;
  }
  const gridLines = $derived([0, 0.5, 1].map((f) => ({ f, val: maxVal * f })));
</script>

{#if values.length === 0}
  <div class="flex h-40 items-center justify-center text-sm text-base-content/40">Tidak ada data chart</div>
{:else}
  <div class="w-full overflow-hidden">
    <svg viewBox="0 0 {width} {height}" class="w-full" preserveAspectRatio="xMidYMid meet" role="img">
      {#each gridLines as g}
        <line x1={padding.left} y1={padding.top + innerH - barHeight(g.val)} x2={width - padding.right} y2={padding.top + innerH - barHeight(g.val)} stroke="#E2E8F0" stroke-width="1" />
        <text x={padding.left - 8} y={padding.top + innerH - barHeight(g.val) + 4} text-anchor="end" font-size="10" fill="#94A3B8">{valueFormat(g.val)}</text>
      {/each}

      {#each values as v, i}
        <rect x={x(i)} y={padding.top + innerH - barHeight(v)} width={barW} height={barHeight(v)} rx="3" fill={color} />
        <text x={x(i) + barW / 2} y={height - 24} text-anchor="middle" font-size="9" fill="#94A3B8">
          {(labels[i] ?? '').length > 10 ? (labels[i] ?? '').slice(0, 9) + '…' : labels[i] ?? ''}
        </text>
      {/each}
    </svg>
  </div>
{/if}
