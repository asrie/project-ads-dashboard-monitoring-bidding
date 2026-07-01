<script lang="ts">
  import PageHeader from '../../lib/components/feedback/PageHeader.svelte';
  import LoadingState from '../../lib/components/feedback/LoadingState.svelte';
  import ErrorState from '../../lib/components/feedback/ErrorState.svelte';
  import { listDomains } from '../../lib/api/domains';
  import { runScan, listScans, getScan } from '../../lib/api/security';
  import { ApiException } from '../../lib/types/api';
  import type { DomainRef, SecurityScanDetail, SecurityScanSummary } from '../../lib/types/models';
  import { user } from '../../lib/stores/auth';
  import { pushToast } from '../../lib/stores/ui';
  import { dateTime } from '../../lib/utils/format';

  let domains = $state<DomainRef[]>([]);
  let selected = $state<string>('');
  let scanning = $state(false);
  let loadingScan = $state(false);
  let result = $state<SecurityScanDetail | null>(null);
  let history = $state<SecurityScanSummary[]>([]);
  let error = $state<string | null>(null);

  const canScan = $derived($user?.role !== 'viewer');

  async function init() {
    try {
      domains = await listDomains();
      if (domains.length && !selected) selected = domains[0].id;
    } catch (e) {
      error = e instanceof ApiException ? e.message : 'Gagal memuat domain.';
    }
  }

  async function loadHistory() {
    if (!selected) return;
    try {
      const res = await listScans({ domain_id: selected, per_page: 10 });
      history = res.items;
    } catch {
      history = [];
    }
  }

  async function onSelectDomain() {
    result = null;
    await loadHistory();
  }

  async function scan() {
    if (!selected) return;
    scanning = true;
    error = null;
    try {
      result = await runScan(selected);
      pushToast(`Scan selesai — grade ${result.grade} (${result.score}).`, 'success');
      await loadHistory();
    } catch (e) {
      const msg = e instanceof ApiException ? e.message : 'Scan gagal.';
      pushToast(msg, 'error');
      error = msg;
    } finally {
      scanning = false;
    }
  }

  async function openScan(id: string) {
    loadingScan = true;
    try {
      result = await getScan(id);
    } catch (e) {
      pushToast(e instanceof ApiException ? e.message : 'Gagal memuat scan.', 'error');
    } finally {
      loadingScan = false;
    }
  }

  function exportJson() {
    if (!result) return;
    const blob = new Blob([JSON.stringify(result, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `security-scan-${result.target_host}-${(result.created_at ?? '').slice(0, 10)}.json`;
    a.click();
    URL.revokeObjectURL(url);
  }

  function gradeClass(g: string | null): string {
    switch (g) {
      case 'A': return 'bg-success text-success-content';
      case 'B': return 'bg-success/80 text-success-content';
      case 'C': return 'bg-warning text-warning-content';
      case 'D': return 'bg-error/80 text-error-content';
      default: return 'bg-error text-error-content';
    }
  }

  $effect(() => { init(); });
  $effect(() => { if (selected) loadHistory(); });

  const r = $derived(result?.results ?? {});
</script>

<PageHeader
  title="Security Site Inspector"
  description="OSINT & security scanner untuk domain milik Anda: DNS, SSL, headers, WHOIS, ports, robots.txt, DNSSEC, HTTP/2-3, dan tech stack." />

<!-- Controls -->
<div class="surface-card mb-5">
  <div class="card-body flex flex-row flex-wrap items-end gap-3 p-4">
    <label class="form-control min-w-60 flex-1">
      <span class="label-text mb-1 text-xs text-base-content/60">Domain</span>
      <select class="select select-bordered select-sm" bind:value={selected} onchange={onSelectDomain}>
        {#each domains as d}
          <option value={d.id}>{d.name} — {d.url}</option>
        {/each}
      </select>
    </label>
    <button class="btn btn-primary btn-sm gap-2" onclick={scan} disabled={!canScan || scanning || !selected}>
      {#if scanning}<span class="loading loading-spinner loading-xs"></span>{/if}
      {scanning ? 'Memindai…' : 'Jalankan Scan'}
    </button>
    {#if result}
      <button class="btn btn-outline btn-sm" onclick={exportJson}>Export JSON</button>
    {/if}
    {#if !canScan}
      <span class="text-xs text-base-content/50">Role Anda read-only — tak bisa menjalankan scan.</span>
    {/if}
  </div>
</div>

{#if scanning}
  <LoadingState label="Memindai target (DNS, SSL, ports, WHOIS…) — ~15 detik" />
{:else if error && !result}
  <ErrorState message={error} onRetry={scan} />
{:else if loadingScan}
  <LoadingState />
{:else if result}
  {@const ssl = r.ssl ?? {}}
  {@const headers = r.headers ?? {}}
  {@const hv = r.http_versions ?? {}}
  {@const dns = r.dns?.records ?? {}}
  {@const whois = r.whois ?? {}}
  {@const ports = r.ports ?? {}}
  {@const robots = r.robots ?? {}}
  {@const dnssec = r.dnssec ?? {}}
  {@const tech = r.tech_stack?.detected ?? []}

  <!-- Score / grade -->
  <div class="surface-card mb-4">
    <div class="card-body flex flex-row items-center gap-5 p-5">
      <div class="flex h-20 w-20 items-center justify-center rounded-2xl text-4xl font-black {gradeClass(result.grade)}">
        {result.grade}
      </div>
      <div>
        <p class="text-lg font-bold">{result.target_host}</p>
        <p class="text-sm text-base-content/60">Security score: <span class="font-semibold">{result.score}/100</span> · {dateTime(result.created_at)}</p>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <!-- SSL -->
    <div class="surface-card"><div class="card-body p-5">
      <h2 class="card-title text-base">🔒 SSL / TLS</h2>
      {#if ssl.ok === false}
        <p class="text-sm text-error">{ssl.error}</p>
      {:else}
        <dl class="text-sm space-y-1">
          <div class="flex justify-between"><dt class="text-base-content/60">Valid</dt><dd>{ssl.valid ? '✅ Ya' : '❌ Tidak'}</dd></div>
          <div class="flex justify-between"><dt class="text-base-content/60">Issuer</dt><dd>{ssl.issuer ?? '–'}</dd></div>
          <div class="flex justify-between"><dt class="text-base-content/60">Berlaku s/d</dt><dd>{dateTime(ssl.valid_to)}</dd></div>
          <div class="flex justify-between"><dt class="text-base-content/60">Sisa hari</dt><dd class="num {ssl.days_remaining < 14 ? 'text-error' : ''}">{ssl.days_remaining ?? '–'}</dd></div>
        </dl>
      {/if}
    </div></div>

    <!-- Security headers -->
    <div class="surface-card"><div class="card-body p-5">
      <h2 class="card-title text-base">🛡️ Security Headers</h2>
      <div class="flex flex-wrap gap-1.5">
        {#each Object.keys(headers.present ?? {}) as h}
          <span class="badge badge-success badge-sm">{h}</span>
        {/each}
        {#each headers.missing ?? [] as h}
          <span class="badge badge-error badge-outline badge-sm">{h} ✕</span>
        {/each}
      </div>
      {#if headers.server}<p class="mt-2 text-xs text-base-content/50">Server: {headers.server}</p>{/if}
    </div></div>

    <!-- HTTP versions -->
    <div class="surface-card"><div class="card-body p-5">
      <h2 class="card-title text-base">⚡ HTTP/2 &amp; HTTP/3</h2>
      <dl class="text-sm space-y-1">
        <div class="flex justify-between"><dt class="text-base-content/60">Negosiasi</dt><dd>HTTP/{hv.negotiated}</dd></div>
        <div class="flex justify-between"><dt class="text-base-content/60">HTTP/2</dt><dd>{hv.http2 ? '✅' : '❌'}</dd></div>
        <div class="flex justify-between"><dt class="text-base-content/60">HTTP/3</dt><dd>{hv.http3 ? '✅' : '❌'}</dd></div>
        {#if hv.alt_svc}<div class="text-xs text-base-content/50 break-all">Alt-Svc: {hv.alt_svc}</div>{/if}
      </dl>
    </div></div>

    <!-- DNSSEC -->
    <div class="surface-card"><div class="card-body p-5">
      <h2 class="card-title text-base">🔐 DNSSEC</h2>
      <p class="text-sm">Status: {dnssec.enabled ? '✅ Aktif' : '❌ Tidak aktif'}</p>
      <p class="text-xs text-base-content/50">DS records: {dnssec.ds_records ?? 0} · AD flag: {dnssec.authenticated_data ? 'yes' : 'no'}</p>
    </div></div>

    <!-- DNS -->
    <div class="surface-card"><div class="card-body p-5">
      <h2 class="card-title text-base">🌐 DNS Records</h2>
      <div class="space-y-1 text-sm">
        {#each Object.entries(dns) as [type, values]}
          <div class="flex gap-2"><span class="badge badge-outline badge-sm">{type}</span>
            <span class="break-all text-base-content/70">{(values as string[]).join(', ')}</span></div>
        {/each}
        {#if Object.keys(dns).length === 0}<p class="text-base-content/40">Tidak ada record.</p>{/if}
      </div>
    </div></div>

    <!-- WHOIS -->
    <div class="surface-card"><div class="card-body p-5">
      <h2 class="card-title text-base">📇 WHOIS (RDAP)</h2>
      <dl class="text-sm space-y-1">
        <div class="flex justify-between"><dt class="text-base-content/60">Registrar</dt><dd>{whois.registrar ?? '–'}</dd></div>
        <div class="flex justify-between"><dt class="text-base-content/60">Dibuat</dt><dd>{whois.created ? dateTime(whois.created) : '–'}</dd></div>
        <div class="flex justify-between"><dt class="text-base-content/60">Kedaluwarsa</dt><dd>{whois.expires ? dateTime(whois.expires) : '–'}</dd></div>
        {#if whois.nameservers?.length}<div class="text-xs text-base-content/50">NS: {whois.nameservers.join(', ')}</div>{/if}
      </dl>
    </div></div>

    <!-- Ports -->
    <div class="surface-card"><div class="card-body p-5">
      <h2 class="card-title text-base">🔌 Open Ports</h2>
      <div class="flex flex-wrap gap-1.5">
        {#each ports.open ?? [] as p}
          <span class="badge badge-sm {p.risky ? 'badge-error' : 'badge-success'}">{p.port} {p.service}</span>
        {/each}
        {#if (ports.open ?? []).length === 0}<span class="text-sm text-base-content/40">Tidak ada port terbuka terdeteksi.</span>{/if}
      </div>
      <p class="mt-2 text-xs text-base-content/50">Dipindai: {(ports.scanned ?? []).join(', ')}</p>
    </div></div>

    <!-- robots.txt -->
    <div class="surface-card"><div class="card-body p-5">
      <h2 class="card-title text-base">🤖 robots.txt</h2>
      {#if robots.exists}
        <dl class="text-sm space-y-1">
          <div class="flex justify-between"><dt class="text-base-content/60">Disallow rules</dt><dd class="num">{robots.disallow_rules}</dd></div>
          <div class="flex justify-between"><dt class="text-base-content/60">Sitemaps</dt><dd class="num">{(robots.sitemaps ?? []).length}</dd></div>
        </dl>
      {:else}
        <p class="text-sm text-base-content/40">Tidak ditemukan (status {robots.status ?? '–'}).</p>
      {/if}
    </div></div>

    <!-- Tech stack -->
    <div class="surface-card lg:col-span-2"><div class="card-body p-5">
      <h2 class="card-title text-base">🧩 Technology Stack</h2>
      <div class="flex flex-wrap gap-1.5">
        {#each tech as t}
          <span class="badge badge-info badge-outline badge-sm">{t.name}</span>
        {/each}
        {#if tech.length === 0}<span class="text-sm text-base-content/40">Tidak terdeteksi.</span>{/if}
      </div>
    </div></div>
  </div>
{:else}
  <div class="surface-card"><div class="card-body p-8 text-center text-sm text-base-content/50">
    Pilih domain lalu klik <b>Jalankan Scan</b>, atau buka salah satu riwayat di bawah.
  </div></div>
{/if}

<!-- History -->
<div class="mt-6">
  <h2 class="mb-3 text-lg font-semibold">Riwayat Scan</h2>
  <div class="surface-card"><div class="card-body p-0">
    {#if history.length === 0}
      <p class="p-6 text-center text-sm text-base-content/50">Belum ada riwayat untuk domain ini.</p>
    {:else}
      <div class="overflow-x-auto">
        <table class="table table-sm">
          <thead class="bg-base-200"><tr>
            <th>Waktu</th><th>Host</th><th class="text-center">Grade</th><th class="text-right">Score</th><th></th>
          </tr></thead>
          <tbody>
            {#each history as h}
              <tr class="hover">
                <td>{dateTime(h.created_at)}</td>
                <td>{h.target_host}</td>
                <td class="text-center"><span class="badge badge-sm {gradeClass(h.grade)}">{h.grade}</span></td>
                <td class="num">{h.score}</td>
                <td class="text-right"><button class="btn btn-xs btn-ghost" onclick={() => openScan(h.id)}>Lihat</button></td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    {/if}
  </div></div>
</div>
