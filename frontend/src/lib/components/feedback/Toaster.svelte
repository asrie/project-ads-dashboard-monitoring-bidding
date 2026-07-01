<script lang="ts">
  import { toasts, dismissToast, type ToastType } from '../../stores/ui';

  const alertClass: Record<ToastType, string> = {
    success: 'alert-success',
    error: 'alert-error',
    info: 'alert-info',
    warning: 'alert-warning',
  };
</script>

<div class="toast toast-end z-50">
  {#each $toasts as toast (toast.id)}
    <div class="alert {alertClass[toast.type]} text-sm shadow-lg">
      <span>{toast.message}</span>
      <button class="btn btn-ghost btn-xs" onclick={() => dismissToast(toast.id)}>✕</button>
    </div>
  {/each}
</div>
