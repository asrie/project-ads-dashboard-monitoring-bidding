<script lang="ts">
  import type { Snippet } from 'svelte';
  import type { PaginationMeta } from '../../types/api';
  import Badge from '../feedback/Badge.svelte';
  import LoadingState from '../feedback/LoadingState.svelte';
  import EmptyState from '../feedback/EmptyState.svelte';
  import ErrorState from '../feedback/ErrorState.svelte';

  export interface Column {
    key: string;
    label: string;
    align?: 'left' | 'right' | 'center';
    badge?: boolean;
    sortable?: boolean;
    format?: (value: unknown, row: Record<string, unknown>) => string;
  }

  let {
    columns,
    rows = [],
    loading = false,
    error = null,
    pagination = undefined,
    sortBy = '',
    sortDir = 'desc',
    onSort,
    onPage,
    onRetry,
    actions,
  }: {
    columns: Column[];
    rows?: Record<string, unknown>[];
    loading?: boolean;
    error?: string | null;
    pagination?: PaginationMeta;
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
    onSort?: (key: string) => void;
    onPage?: (page: number) => void;
    onRetry?: () => void;
    actions?: Snippet<[Record<string, unknown>]>;
  } = $props();

  function alignClass(col: Column): string {
    if (col.align === 'right') return 'text-right num';
    if (col.align === 'center') return 'text-center';
    return 'text-left';
  }

  function display(col: Column, row: Record<string, unknown>): string {
    const v = row[col.key];
    if (col.format) return col.format(v, row);
    if (v === null || v === undefined || v === '') return '–';
    return String(v);
  }
</script>

<div class="surface-card">
  <div class="card-body p-0">
    {#if error}
      <ErrorState message={error} {onRetry} />
    {:else if loading}
      <LoadingState />
    {:else if rows.length === 0}
      <EmptyState />
    {:else}
      <div class="overflow-x-auto">
        <table class="table table-sm">
          <thead class="sticky top-0 bg-base-200">
            <tr>
              {#each columns as col}
                <th class="{alignClass(col)} text-xs uppercase text-base-content/60">
                  {#if col.sortable && onSort}
                    <button class="inline-flex items-center gap-1 hover:text-primary" onclick={() => onSort?.(col.key)}>
                      {col.label}
                      {#if sortBy === col.key}<span>{sortDir === 'asc' ? '▲' : '▼'}</span>{/if}
                    </button>
                  {:else}
                    {col.label}
                  {/if}
                </th>
              {/each}
              {#if actions}<th class="text-right text-xs uppercase text-base-content/60">Aksi</th>{/if}
            </tr>
          </thead>
          <tbody>
            {#each rows as row}
              <tr class="hover">
                {#each columns as col}
                  <td class={alignClass(col)}>
                    {#if col.badge}
                      <Badge status={String(row[col.key] ?? '')} />
                    {:else}
                      {display(col, row)}
                    {/if}
                  </td>
                {/each}
                {#if actions}
                  <td class="text-right">{@render actions(row)}</td>
                {/if}
              </tr>
            {/each}
          </tbody>
        </table>
      </div>

      {#if pagination && pagination.last_page > 1}
        <div class="flex items-center justify-between border-t border-base-300 px-4 py-3 text-sm">
          <span class="text-base-content/60">
            Halaman {pagination.current_page} dari {pagination.last_page} · {pagination.total} baris
          </span>
          <div class="join">
            <button
              class="btn btn-sm join-item"
              disabled={pagination.current_page <= 1}
              onclick={() => onPage?.(pagination.current_page - 1)}>«</button>
            <button class="btn btn-sm join-item no-animation">{pagination.current_page}</button>
            <button
              class="btn btn-sm join-item"
              disabled={pagination.current_page >= pagination.last_page}
              onclick={() => onPage?.(pagination.current_page + 1)}>»</button>
          </div>
        </div>
      {/if}
    {/if}
  </div>
</div>
