<script lang="ts">
  import { user } from '../../stores/auth';
  import { logout } from '../../api/auth';
  import { push } from 'svelte-spa-router';
  import { pushToast } from '../../stores/ui';

  async function handleLogout() {
    await logout();
    pushToast('Anda telah keluar.', 'info');
    push('/login');
  }

  const initials = $derived(
    ($user?.name ?? 'U')
      .split(' ')
      .map((s) => s[0])
      .slice(0, 2)
      .join('')
      .toUpperCase()
  );
</script>

<header class="flex h-16 shrink-0 items-center justify-between border-b border-base-300 bg-base-100 px-6">
  <div>
    <p class="text-sm font-semibold text-base-content">Dashboard Monitoring</p>
    <p class="text-[11px] text-base-content/50">Programmatic Ads &amp; Website Performance</p>
  </div>

  <div class="dropdown dropdown-end">
    <div tabindex="0" role="button" class="flex items-center gap-3">
      <div class="text-right leading-tight">
        <p class="text-sm font-medium text-base-content">{$user?.name ?? 'User'}</p>
        <p class="text-[11px] text-base-content/50">{$user?.role_label ?? ''}</p>
      </div>
      <div class="avatar avatar-placeholder">
        <div class="w-9 rounded-full bg-primary text-primary-content">
          <span class="text-sm">{initials}</span>
        </div>
      </div>
    </div>
    <ul tabindex="-1" class="menu dropdown-content z-50 mt-2 w-44 rounded-box border border-base-300 bg-base-100 p-2 shadow">
      <li class="menu-title text-xs">{$user?.email ?? ''}</li>
      <li><button onclick={handleLogout}>Keluar</button></li>
    </ul>
  </div>
</header>
