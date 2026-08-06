<script>
    import { inertia } from '@inertiajs/svelte';

    let {
        paginator = null,
        data = null,
        links: rawLinksProp = null,
        itemLabel = 'Produk',
        class: className = '',
        primary = null,
    } = $props();

    const activePaginator = $derived(paginator || data || {});
    const rawLinks = $derived(activePaginator.links || rawLinksProp || []);

    const prevLink = $derived(
        rawLinks.find(
            (l) =>
                l.label.toLowerCase().includes('previous') ||
                l.label.toLowerCase().includes('prev') ||
                l.label.includes('&laquo;'),
        ),
    );

    const nextLink = $derived(
        rawLinks.find(
            (l) =>
                l.label.toLowerCase().includes('next') ||
                l.label.includes('&raquo;'),
        ),
    );

    const numericLinks = $derived.by(() => {
        return rawLinks.filter((l) => l !== prevLink && l !== nextLink);
    });

    const windowedLinks = $derived.by(() => {
        const total = numericLinks.length;
        if (total <= 5) {
            return numericLinks;
        }

        const activeIndex = numericLinks.findIndex((l) => l.active);
        if (activeIndex === -1) return numericLinks.slice(0, 5);

        const result = [];
        for (let i = 0; i < total; i++) {
            const isFirst = i === 0;
            const isLast = i === total - 1;
            const isAroundActive = Math.abs(i - activeIndex) <= 1;

            if (isFirst || isLast || isAroundActive) {
                result.push(numericLinks[i]);
            } else {
                const lastAdded = result[result.length - 1];
                if (!lastAdded || lastAdded.type !== 'ellipsis') {
                    result.push({
                        url: null,
                        label: '...',
                        active: false,
                        type: 'ellipsis',
                    });
                }
            }
        }
        return result;
    });

    const activeStyle = $derived(
        primary
            ? `background-color: ${primary}; border-color: ${primary}; color: #ffffff;`
            : '',
    );
</script>

{#if activePaginator && rawLinks && rawLinks.length > 3}
    <div
        class={className ||
            'p-4 sm:p-5 border-t border-slate-150 flex flex-col sm:flex-row gap-3.5 sm:items-center sm:justify-between bg-slate-50/50 rounded-b-2xl'}
    >
        <!-- Info Text -->
        <p class="text-xs text-slate-500 font-medium tracking-wide">
            Menampilkan <span class="font-bold text-slate-800"
                >{activePaginator.from || 0} - {activePaginator.to || 0}</span
            > dari
            <span class="font-bold text-slate-800"
                >{activePaginator.total || 0}</span
            >
            {itemLabel}
        </p>

        <!-- Pagination Controls -->
        <nav
            aria-label="Pagination"
            class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-none"
        >
            <!-- Prev Button -->
            {#if prevLink}
                <a
                    href={prevLink.url || '#'}
                    use:inertia={{ preserveScroll: true }}
                    class="w-9 h-9 rounded-xl flex items-center justify-center text-xs font-semibold transition flex-shrink-0 border border-slate-200/80 bg-white text-slate-600 hover:bg-slate-100 hover:text-slate-900 hover:border-slate-300 shadow-2xs {!prevLink.url
                        ? 'opacity-40 cursor-not-allowed pointer-events-none'
                        : ''}"
                    title="Halaman Sebelumnya"
                    aria-label="Halaman Sebelumnya"
                >
                    <i class="ti ti-chevron-left text-sm font-bold"></i>
                </a>
            {/if}

            <!-- Page Number Links -->
            {#each windowedLinks as link}
                {#if link.type === 'ellipsis' || link.label === '...'}
                    <span
                        class="min-w-[32px] h-9 flex items-center justify-center text-xs font-bold text-slate-400 select-none flex-shrink-0"
                    >
                        ...
                    </span>
                {:else}
                    <a
                        href={link.url || '#'}
                        use:inertia={{ preserveScroll: true }}
                        style={link.active ? activeStyle : ''}
                        class="min-w-[36px] h-9 px-2.5 rounded-xl flex items-center justify-center text-xs font-bold transition flex-shrink-0 {link.active
                            ? (primary ? 'shadow-md shadow-slate-900/10' : 'bg-slate-900 text-white shadow-sm ring-2 ring-slate-900/20')
                            : 'bg-white border border-slate-200/80 text-slate-700 hover:bg-slate-100 hover:text-slate-900 hover:border-slate-300 shadow-2xs'} {!link.url
                            ? 'opacity-40 cursor-not-allowed pointer-events-none'
                            : ''}"
                    >
                        {@html link.label}
                    </a>
                {/if}
            {/each}

            <!-- Next Button -->
            {#if nextLink}
                <a
                    href={nextLink.url || '#'}
                    use:inertia={{ preserveScroll: true }}
                    class="w-9 h-9 rounded-xl flex items-center justify-center text-xs font-semibold transition flex-shrink-0 border border-slate-200/80 bg-white text-slate-600 hover:bg-slate-100 hover:text-slate-900 hover:border-slate-300 shadow-2xs {!nextLink.url
                        ? 'opacity-40 cursor-not-allowed pointer-events-none'
                        : ''}"
                    title="Halaman Selanjutnya"
                    aria-label="Halaman Selanjutnya"
                >
                    <i class="ti ti-chevron-right text-sm font-bold"></i>
                </a>
            {/if}
        </nav>
    </div>
{/if}
