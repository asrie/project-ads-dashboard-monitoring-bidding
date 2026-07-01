<script lang="ts">
  import { link, location } from 'svelte-spa-router';

  const nav = [
    { href: '/dashboard', label: 'Overview', icon: '📊' },
    { href: '/slots', label: 'Slot Performance', icon: '🎯' },
    { href: '/bidders', label: 'Bidding', icon: '💰' },
    { href: '/prebid', label: 'Prebid Health', icon: '⚡' },
    { href: '/gam', label: 'GAM Monitoring', icon: '🛰️' },
    { href: '/server', label: 'Server Health', icon: '🖥️' },
    { href: '/network-ads', label: 'Network Ads', icon: '🌐' },
    { href: '/web-vitals', label: 'Web Core Vitals', icon: '📈' },
    { href: '/ad-layout', label: 'Ad Layout', icon: '📱' },
    { href: '/security', label: 'Security Inspector', icon: '🛡️' },
    { href: '/alerts', label: 'Alerts & Insights', icon: '🔔' },
  ];

  function isActive(href: string): boolean {
    return $location === href || $location.startsWith(href + '/');
  }
</script>

<aside class="flex h-full w-64 shrink-0 flex-col border-r border-base-300 bg-base-100">
  <div class="flex items-center gap-2 border-b border-base-300 px-5 py-4">
    <span class="text-xl">🛡️</span>
    <div class="leading-tight">
      <p class="text-sm font-bold text-primary">Ads Monitoring</p>
      <p class="text-[11px] text-base-content/50">Programmatic Revenue</p>
    </div>
  </div>

  <nav class="flex-1 space-y-1 overflow-y-auto p-3">
    {#each nav as item}
      <a href={'#' + item.href} use:link class="nav-link {isActive(item.href) ? 'nav-link-active' : ''}">
        <span class="text-base">{item.icon}</span>
        <span>{item.label}</span>
      </a>
    {/each}
  </nav>

  <div class="border-t border-base-300 p-3 text-[11px] text-base-content/40">
    v0.1.0 · samurai-blue
  </div>
</aside>
