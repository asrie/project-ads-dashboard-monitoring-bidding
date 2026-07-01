<script lang="ts">
  import { push } from 'svelte-spa-router';
  import { login } from '../../lib/api/auth';
  import { ApiException } from '../../lib/types/api';
  import { pushToast } from '../../lib/stores/ui';

  let email = $state('');
  let password = $state('');
  let loading = $state(false);
  let error = $state<string | null>(null);

  async function submit(e: Event) {
    e.preventDefault();
    loading = true;
    error = null;
    try {
      const u = await login(email, password);
      pushToast(`Selamat datang, ${u.name}.`, 'success');
      push('/dashboard');
    } catch (err) {
      error = err instanceof ApiException ? err.errors[0]?.message ?? err.message : 'Login gagal.';
    } finally {
      loading = false;
    }
  }
</script>

<div class="flex min-h-screen items-center justify-center bg-base-200 p-4">
  <div class="w-full max-w-md">
    <div class="mb-6 text-center">
      <div class="text-4xl">🛡️</div>
      <h1 class="mt-2 text-2xl font-bold text-primary">Ads Monitoring Dashboard</h1>
      <p class="text-sm text-base-content/60">Programmatic Ads &amp; Website Performance</p>
    </div>

    <div class="surface-card">
      <form class="card-body gap-4" onsubmit={submit}>
        <h2 class="card-title text-base-content">Masuk</h2>

        {#if error}
          <div class="alert alert-error py-2 text-sm">{error}</div>
        {/if}

        <label class="form-control">
          <span class="label-text mb-1 text-sm">Email</span>
          <input
            type="email"
            class="input input-bordered"
            placeholder="nama@kgmedia.io"
            bind:value={email}
            required
            autocomplete="username" />
        </label>

        <label class="form-control">
          <span class="label-text mb-1 text-sm">Password</span>
          <input
            type="password"
            class="input input-bordered"
            placeholder="••••••••"
            bind:value={password}
            required
            autocomplete="current-password" />
        </label>

        <button type="submit" class="btn btn-primary mt-2" disabled={loading}>
          {#if loading}<span class="loading loading-spinner loading-sm"></span>{/if}
          Masuk
        </button>
      </form>
    </div>
    <p class="mt-4 text-center text-xs text-base-content/40">© 2026 KG Media · Programmatic Revenue Team</p>
  </div>
</div>
