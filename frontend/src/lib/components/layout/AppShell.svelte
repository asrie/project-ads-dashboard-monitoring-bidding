<script lang="ts">
  import type { Snippet } from 'svelte';
  import Sidebar from './Sidebar.svelte';
  import Topbar from './Topbar.svelte';

  let { children }: { children: Snippet } = $props();
</script>

<div class="flex h-screen overflow-hidden">
  <Sidebar />
  <div class="flex flex-1 flex-col overflow-hidden">
    <Topbar />
    <main class="flex-1 overflow-y-auto bg-base-200 p-6">
      {@render children()}
    </main>
  </div>
</div>
