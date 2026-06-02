<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';

    let { movements, filters = {}, storeName = '', storeLogo = '' } = $props();

    const primary = $derived((page.props as any).theme?.primary_color ?? '#0c4cb4');

    // svelte-ignore state_referenced_locally
    let filterType = $state((filters as any).type ?? '');
    // svelte-ignore state_referenced_locally
    let filterDateFrom = $state((filters as any).date_from ?? '');
    // svelte-ignore state_referenced_locally
    let filterDateTo = $state((filters as any).date_to ?? '');

    function applyFilters() {
        router.get('/admin/stock-movements', {
            type: filterType || undefined,
            date_from: filterDateFrom || undefined,
            date_to: filterDateTo || undefined,
        }, { preserveScroll: true });
    }

    function resetFilters() {
        filterType = '';
        filterDateFrom = '';
        filterDateTo = '';
        router.get('/admin/stock-movements');
    }

    function fmtDate(dateStr: string): string {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    const typeColors: Record<string, { bg: string; text: string; icon: string }> = {
        masuk: { bg: '#dcfce7', text: '#166534', icon: 'ti-arrow-bar-to-up' },
        keluar: { bg: '#fee2e2', text: '#991b1b', icon: 'ti-arrow-bar-to-down' },
        retur: { bg: '#dbeafe', text: '#1e40af', icon: 'ti-refresh' },
        penyesuaian: { bg: '#fef3c7', text: '#92400e', icon: 'ti-adjustments' },
    };

    const typeLabels: Record<string, string> = {
        masuk: 'Stok Masuk',
        keluar: 'Stok Keluar',
        retur: 'Retur',
        penyesuaian: 'Penyesuaian',
    };
</script>

<AdminLayout>
    <div class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="font-outfit font-black text-2xl text-slate-800">Laporan Stok Keluar</h3>
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1">Riwayat pergerakan stok produk</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-card p-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <p class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 font-outfit">Tipe Pergerakan</p>
                        <select
                            bind:value={filterType}
                            class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition appearance-none"
                        >
                            <option value="">Semua Tipe</option>
                            {#each Object.entries(typeLabels) as [key, label]}
                                <option value={key}>{label}</option>
                            {/each}
                        </select>
                    </div>
                    <div>
                        <p class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 font-outfit">Dari Tanggal</p>
                        <input
                            type="date"
                            bind:value={filterDateFrom}
                            class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition"
                        />
                    </div>
                    <div>
                        <p class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 font-outfit">Sampai Tanggal</p>
                        <input
                            type="date"
                            bind:value={filterDateTo}
                            class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition"
                        />
                    </div>
                </div>
                <div class="flex gap-2 mt-4">
                    <button
                        onclick={applyFilters}
                        class="px-5 py-2.5 rounded-xl text-xs font-bold text-white transition font-outfit uppercase tracking-wider shadow-lg"
                        style="background:{primary}; box-shadow: 0 4px 12px -2px {primary}40;"
                    >
                        <i class="ti ti-filter mr-1 text-sm"></i>Filter
                    </button>
                    <button
                        onclick={resetFilters}
                        class="px-5 py-2.5 rounded-xl text-xs font-bold text-slate-500 border border-slate-200 hover:bg-slate-50 transition font-outfit uppercase tracking-wider"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden">
                {#if movements.data.length === 0}
                    <div class="flex flex-col items-center justify-center py-16">
                        <i class="ti ti-package-off text-5xl text-slate-200 mb-3"></i>
                        <p class="text-slate-500 font-semibold">Tidak ada data pergerakan stok</p>
                    </div>
                {:else}
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">
                                    <th class="py-6 px-6">Produk</th>
                                    <th class="py-6 px-6">Tipe</th>
                                    <th class="py-6 px-6 text-center">Qty</th>
                                    <th class="py-6 px-6 text-center">Sebelum</th>
                                    <th class="py-6 px-6 text-center">Sesudah</th>
                                    <th class="py-6 px-6">Transaksi</th>
                                    <th class="py-6 px-6">Keterangan</th>
                                    <th class="py-6 px-6">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700 text-sm font-medium">
                                {#each movements.data as mov}
                                    {@const typeStyle = typeColors[mov.type] ?? { bg: '#f1f5f9', text: '#475569', icon: 'ti-circle' }}
                                    <tr class="hover:bg-slate-50/50 transition duration-150 border-b border-slate-100">
                                        <td class="py-6 px-6">
                                            <p class="font-bold text-slate-800 whitespace-pre-wrap break-words">{mov.product?.name ?? '-'}</p>
                                            {#if mov.productVariant}
                                                <p class="text-[11px] text-slate-400 font-bold mt-0.5 break-all">SKU: {mov.productVariant.sku}</p>
                                            {:else if mov.product?.sku}
                                                <p class="text-[11px] text-slate-400 font-bold mt-0.5 break-all">SKU: {mov.product.sku}</p>
                                            {/if}
                                        </td>
                                        <td class="py-6 px-6">
                                            <span
                                                class="inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-wider"
                                                style="background:{typeStyle.bg}; color:{typeStyle.text}"
                                            >
                                                <i class="ti {typeStyle.icon} text-xs"></i>
                                                {typeLabels[mov.type] ?? mov.type}
                                            </span>
                                        </td>
                                        <td class="py-6 px-6 text-center">
                                            <span
                                                class="font-black text-sm"
                                                style="color:{mov.quantity > 0 ? '#166534' : '#991b1b'}"
                                            >
                                                {mov.quantity > 0 ? '+' : ''}{mov.quantity}
                                            </span>
                                        </td>
                                        <td class="py-6 px-6 text-center">
                                            <span class="text-sm font-bold text-slate-700">{mov.stock_before}</span>
                                        </td>
                                        <td class="py-6 px-6 text-center">
                                            <span class="text-sm font-bold text-slate-700">{mov.stock_after}</span>
                                        </td>
                                        <td class="py-6 px-6">
                                            {#if mov.transaction}
                                                <a
                                                    href="/admin/transactions/{mov.transaction.id}"
                                                    class="text-xs font-mono font-bold hover:underline break-all"
                                                    style="color:{primary}"
                                                >
                                                    {mov.transaction.transaction_number}
                                                </a>
                                            {:else}
                                                <span class="text-xs text-slate-400 font-bold">-</span>
                                            {/if}
                                        </td>
                                        <td class="py-6 px-6">
                                            <span class="text-xs text-slate-500 font-bold whitespace-pre-wrap break-words">{mov.notes ?? '-'}</span>
                                        </td>
                                        <td class="py-6 px-6">
                                            <span class="text-xs text-slate-500 font-bold">{fmtDate(mov.created_at)}</span>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    {#if movements.last_page > 1}
                        <div class="flex items-center justify-between px-6 py-5 border-t border-slate-100 bg-slate-50/20">
                            <p class="text-xs font-bold text-slate-500 font-outfit">
                                Menampilkan {movements.from}–{movements.to} dari {movements.total} data
                            </p>
                            <div class="flex gap-1">
                                {#if movements.prev_page_url}
                                    <a href={movements.prev_page_url} aria-label="Previous Page" class="w-8 h-8 rounded-lg border border-slate-200 text-slate-500 hover:bg-brand-blueLight hover:text-brand-blueRoyal flex items-center justify-center transition">
                                        <i class="ti ti-chevron-left"></i>
                                    </a>
                                {/if}
                                {#if movements.next_page_url}
                                    <a href={movements.next_page_url} aria-label="Next Page" class="w-8 h-8 rounded-lg border border-slate-200 text-slate-500 hover:bg-brand-blueLight hover:text-brand-blueRoyal flex items-center justify-center transition">
                                        <i class="ti ti-chevron-right"></i>
                                    </a>
                                {/if}
                            </div>
                        </div>
                    {/if}
                {/if}
            </div>
        </div>
    </div>
</AdminLayout>
