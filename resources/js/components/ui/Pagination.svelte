<script>
    import { inertia } from '@inertiajs/svelte';

    let { paginator, data, itemLabel = 'Produk' } = $props();

    const activePaginator = $derived(paginator || data || {});
</script>

{#if activePaginator && activePaginator.links && activePaginator.links.length > 3}
    <div
        class="p-4 sm:p-6 border-t border-slate-100 flex flex-col sm:flex-row gap-4 sm:items-center sm:justify-between"
    >
        <p class="text-xs font-bold text-slate-400">
            Menampilkan {activePaginator.from || 0} - {activePaginator.to || 0} dari
            {activePaginator.total || 0} {itemLabel}
        </p>
        <div class="flex items-center gap-2">
            {#each activePaginator.links as link}
                <a
                    href={link.url || '#'}
                    use:inertia
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold transition {link.active
                        ? 'bg-brand-blueRoyal text-white shadow-sm'
                        : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'} {!link.url
                        ? 'opacity-50 cursor-not-allowed pointer-events-none'
                        : ''}"
                >
                    {#if link.label.toLowerCase().includes('previous') || link.label.toLowerCase().includes('prev') || link.label.includes('&laquo;')}
                        <i class="ti ti-chevron-left text-sm"></i>
                    {:else if link.label.toLowerCase().includes('next') || link.label.includes('&raquo;')}
                        <i class="ti ti-chevron-right text-sm"></i>
                    {:else}
                        {@html link.label}
                    {/if}
                </a>
            {/each}
        </div>
    </div>
{/if}
