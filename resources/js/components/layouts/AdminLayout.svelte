<script lang="ts">
    import { onMount } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import AdminSidebar from './AdminSidebar.svelte';

    let { children } = $props();

    let isSidebarOpen = $state(false);

    function toggleSidebar() {
        isSidebarOpen = !isSidebarOpen;
    }
</script>

<div
    class="min-h-screen flex selection:bg-brand-blueRoyal selection:text-white bg-brand-slateBg font-sans"
    style="--color-brand-blueRoyal: {page.props.theme?.primary_color ||
        '#0c4cb4'}; --color-brand-orange: {page.props.theme?.secondary_color ||
        '#fa7315'};"
>
    <!-- Overlay for mobile sidebar -->
    {#if isSidebarOpen}
        <div
            class="fixed inset-0 bg-slate-900/50 z-40 lg:hidden backdrop-blur-sm"
            onclick={toggleSidebar}
            onkeydown={(e) => e.key === 'Escape' && toggleSidebar()}
            role="button"
            tabindex="0"
        ></div>
    {/if}

    <!-- ==================== SIDEBAR ==================== -->
    <AdminSidebar {isSidebarOpen} />

    <!-- ==================== MAIN LAYOUT ==================== -->
    <div class="flex-grow lg:pl-64 flex flex-col min-h-screen w-full">
        <!-- Topbar -->
        <header
            class="h-20 bg-white/80 backdrop-blur-xl border-b border-slate-200 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-8"
        >
            <div class="flex items-center gap-4 flex-grow">
                <button
                    onclick={toggleSidebar}
                    class="lg:hidden p-2 text-slate-500 hover:text-slate-800 rounded-lg hover:bg-slate-100 transition"
                >
                    <i class="ti ti-menu-2 text-xl"></i>
                </button>
                <div class="flex items-center gap-2 font-outfit hidden sm:flex">
                    <span
                        class="px-2.5 py-1 bg-brand-blueRoyal/10 text-brand-blueRoyal text-[10px] font-black tracking-widest uppercase rounded-lg"
                        >Admin</span
                    >
                    <span class="font-black text-slate-800 tracking-tight"
                        >Bizmate Console</span
                    >
                </div>
            </div>

            <div class="flex items-center gap-4 shrink-0">
                <button
                    class="p-2.5 text-slate-400 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 rounded-full transition relative"
                    aria-label="Notifikasi"
                >
                    <i class="ti ti-bell text-xl"></i>
                    <span
                        class="absolute top-1 right-1 w-2.5 h-2.5 bg-brand-orange rounded-full border-2 border-white"
                    ></span>
                </button>
                <div class="h-6 w-px bg-slate-200 mx-2 hidden sm:block"></div>
                <a
                    href="/"
                    class="hidden sm:flex items-center gap-2 px-4 py-2 bg-slate-50 border border-slate-200 hover:bg-slate-100 text-slate-600 rounded-full text-xs font-bold transition"
                >
                    <span
                        class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"
                    ></span>
                    Live Storefront
                    <i class="ti ti-external-link text-sm"></i>
                </a>
            </div>
        </header>

        <!-- Page Content -->
        {@render children()}
    </div>
</div>

<style>
    :global(.custom-scrollbar::-webkit-scrollbar) {
        width: 4px;
    }
    :global(.custom-scrollbar::-webkit-scrollbar-track) {
        background: transparent;
    }
    :global(.custom-scrollbar::-webkit-scrollbar-thumb) {
        background: #e2e8f0;
        border-radius: 10px;
    }
    :global(.custom-scrollbar::-webkit-scrollbar-thumb:hover) {
        background: #cbd5e1;
    }
</style>
