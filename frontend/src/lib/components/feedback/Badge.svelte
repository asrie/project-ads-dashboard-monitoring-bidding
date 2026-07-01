<script lang="ts">
  // Maps a domain status/severity to a DaisyUI badge (CLAUDE.md badge rules).
  let { status = '', label = '' }: { status?: string; label?: string } = $props();

  const map: Record<string, string> = {
    // health
    healthy: 'badge-success',
    good: 'badge-success',
    warning: 'badge-warning',
    needs_improvement: 'badge-warning',
    critical: 'badge-error',
    poor: 'badge-error',
    // severity
    low: 'badge-info',
    medium: 'badge-warning',
    high: 'badge-error',
    // alert status
    open: 'badge-error',
    acknowledged: 'badge-warning',
    resolved: 'badge-success',
    // server health
    up: 'badge-success',
    down: 'badge-error',
    unknown: 'badge-ghost',
    // generic
    success: 'badge-success',
    failed: 'badge-error',
    empty: 'badge-ghost',
    completed: 'badge-success',
    timeout: 'badge-warning',
    error: 'badge-error',
  };

  const cls = $derived(map[status] ?? 'badge-outline');
  const text = $derived(label || status.replace(/_/g, ' '));
</script>

<span class="badge {cls} badge-sm font-medium capitalize">{text}</span>
