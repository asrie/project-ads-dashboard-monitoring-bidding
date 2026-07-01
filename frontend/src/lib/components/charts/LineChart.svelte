<script lang="ts">
  // Lightweight dependency-free SVG line chart.
  interface Series {
    label: string;
    color: string;
    points: number[];
  }

  let {
    labels = [],
    series = [],
    height = 240,
    valueFormat = (v: number) => String(Math.round(v)),
  }: {
    labels?: string[];
    series?: Series[];
    height?: number;
    valueFormat?: (v: number) => string;
  } = $props();

  const width = 720;
  const padding = { top: 16, right: 16, bottom: 28, left: 48 };

  const innerW = width - padding.left - padding.right;
  const innerH = $derived(height - padding.top - padding.bottom);

  const allValues = $derived(series.flatMap((s) => s.points));
  const maxVal = $derived(allValues.length ? Math.max(...allValues, 0) : 1);
  const minVal = 0;
  const range = $derived(maxVal - minVal || 1);
  const count = $derived(Math.max(labels.length, 1));

  function x(i: number): number {
    if (count === 1) return padding.left + innerW / 2;
    return padding.left + (i / (count - 1)) * innerW;
  }
  function y(v: number): number {
    return padding.top + innerH - ((v - minVal) / range) * innerH;
  }

  function path(points: number[]): string {
    return points.map((v, i) => `${i === 0 ? 'M' : 'L'} ${x(i).toFixed(1)} ${y(v).toFixed(1)}`).join(' ');
  }

  const gridLines = $derived([0, 0.25, 0.5, 0.75, 1].map((f) => ({ f, val: minVal + range * f })));
  // Show at most ~8 x labels to avoid clutter.
  const labelStep = $derived(Math.ceil(count / 8));
</script>

{#if series.length === 0 || count === 0}
  <div class="flex h-40 items-center justify-center text-sm text-base-content/40">Tidak ada data chart</div>
{:else}
  <div class="w-full overflow-hidden">
    <svg viewBox="0 0 {width} {height}" class="w-full" preserveAspectRatio="xMidYMid meet" role="img">
      {#each gridLines as g}
        <line x1={padding.left} y1={y(g.val)} x2={width - padding.right} y2={y(g.val)} stroke="#E2E8F0" stroke-width="1" />
        <text x={padding.left - 8} y={y(g.val) + 4} text-anchor="end" font-size="10" fill="#94A3B8">
          {valueFormat(g.val)}
        </text>
      {/each}

      {#each labels as label, i}
        {#if i % labelStep === 0}
          <text x={x(i)} y={height - 8} text-anchor="middle" font-size="10" fill="#94A3B8">
            {label.length > 5 ? label.slice(5) : label}
          </text>
        {/if}
      {/each}

      {#each series as s}
        <path d={path(s.points)} fill="none" stroke={s.color} stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
        {#each s.points as v, i}
          <circle cx={x(i)} cy={y(v)} r="2.5" fill={s.color} />
        {/each}
      {/each}
    </svg>
  </div>

  {#if series.length > 1}
    <div class="mt-2 flex flex-wrap justify-center gap-4">
      {#each series as s}
        <span class="flex items-center gap-1.5 text-xs text-base-content/60">
          <span class="inline-block h-2 w-3 rounded-sm" style="background:{s.color}"></span>{s.label}
        </span>
      {/each}
    </div>
  {/if}
{/if}
