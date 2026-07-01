<script lang="ts">
  import Router, { location, replace } from 'svelte-spa-router';
  import { isAuthenticated } from './lib/stores/auth';
  import AppShell from './lib/components/layout/AppShell.svelte';
  import Toaster from './lib/components/feedback/Toaster.svelte';

  import Login from './routes/login/Login.svelte';
  import Dashboard from './routes/dashboard/Dashboard.svelte';
  import Slots from './routes/slots/Slots.svelte';
  import Bidders from './routes/bidders/Bidders.svelte';
  import Prebid from './routes/prebid/Prebid.svelte';
  import Gam from './routes/gam/Gam.svelte';
  import Server from './routes/server/Server.svelte';
  import NetworkAds from './routes/network-ads/NetworkAds.svelte';
  import WebVitals from './routes/web-vitals/WebVitals.svelte';
  import AdLayout from './routes/ad-layout/AdLayout.svelte';
  import Security from './routes/security/Security.svelte';
  import Alerts from './routes/alerts/Alerts.svelte';

  const appRoutes = {
    '/dashboard': Dashboard,
    '/slots': Slots,
    '/bidders': Bidders,
    '/prebid': Prebid,
    '/gam': Gam,
    '/server': Server,
    '/network-ads': NetworkAds,
    '/web-vitals': WebVitals,
    '/ad-layout': AdLayout,
    '/security': Security,
    '/alerts': Alerts,
    '*': Dashboard,
  };

  const publicRoutes = {
    '/login': Login,
    '*': Login,
  };

  // Client-side auth guard. Backend remains the source of truth (CLAUDE.md).
  $effect(() => {
    if (!$isAuthenticated && !$location.startsWith('/login')) {
      replace('/login');
    } else if ($isAuthenticated && ($location === '/' || $location === '' || $location.startsWith('/login'))) {
      replace('/dashboard');
    }
  });
</script>

{#if $isAuthenticated}
  <AppShell>
    <Router routes={appRoutes} />
  </AppShell>
{:else}
  <Router routes={publicRoutes} />
{/if}

<Toaster />
