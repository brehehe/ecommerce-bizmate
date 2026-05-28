<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';

    let { movements, filters = {}, storeName = '', storeLogo = '' } = $props();

    const primary = $derived((page.props as any).theme?.primary_color ?? '#0c4cb4');

    let filterType = $state((filters as any).type ?? '');
    let filterDateFrom = $state((filters as any).date_from ?? '');
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

<AdminLayout {storeName} {storeLogo}>
    <div class="space-y-6">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-black text-slate-800">Laporan Stok Keluar</h1>
            <p class="text-sm text-slate-500 mt-1">Riwayat pergerakan stok produk</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Tipe Pergerakan</label>
                    <select
                        bind:value={filterType}
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none appearance-none bg-white"
                    >
                        <option value="">Semua Tipe</option>
                        {#each Object.entries(typeLabels) as [key, label]}
                            <option value={key}>{label}</option>
                        {/each}
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Dari Tanggal</label>
                    <input
                        type="date"
                        bind:value={filterDateFrom}
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none"
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Sampai Tanggal</label>
                    <input
                        type="date"
                        bind:value={filterDateTo}
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none"
                    />
                </div>
            </div>
            <div class="flex gap-2 mt-3">
                <button onclick={applyFilters} class="px-4 py-2 rounded-xl text-sm font-bold text-white transition" style="background:{primary}">
                    <i class="ti ti-filter mr-1"></i>Filter
                </button>
                <button onclick={resetFilters} class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 border border-slate-200 hover:bg-slate-50 transition">
                    Reset
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            {#if movements.data.length === 0}
                <div class="flex flex-col items-center justify-center py-16">
                    <i class="ti ti-package-off text-5xl text-slate-200 mb-3"></i>
                    <p class="text-slate-500 font-semibold">Tidak ada data pergerakan stok</p>
                </div>
            {:else}
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Produk</th>
                                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Tipe</th>
                                <th class="text-center px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Qty</th>
                                <th class="text-center px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Sebelum</th>
                                <th class="text-center px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Sesudah</th>
                                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Transaksi</th>
                                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Keterangan</th>
                                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            {#each movements.data as mov}
                                {@const typeStyle = typeColors[mov.type] ?? { bg: '#f1f5f9', text: '#475569', icon: 'ti-circle' }}
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-800 text-sm">{mov.product?.name ?? '-'}</p>
                                        {#if mov.productVariant}
                                            <p class="text-xs text-slate-400">SKU: {mov.productVariant.sku}</p>
                                        {:else if mov.product?.sku}
                                            <p class="text-xs text-slate-400">SKU: {mov.product.sku}</p>
                                        {/if}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center gap-1 text-[10px] font-black px-2 py-1 rounded-full"
                                            style="background:{typeStyle.bg}; color:{typeStyle.text}"
                                        >
                                            <i class="ti {typeStyle.icon} text-xs"></i>
                                            {typeLabels[mov.type] ?? mov.type}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="font-black text-sm"
                                            style="color:{mov.quantity > 0 ? '#166534' : '#991b1b'}"
                                        >
                                            {mov.quantity > 0 ? '+' : ''}{mov.quantity}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="text-sm font-semibold text-slate-700">{mov.stock_before}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="text-sm font-semibold text-slate-700">{mov.stock_after}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        {#if mov.transaction}
                                            <a
                                                href="/admin/transactions/{mov.transaction.id}"
                                                class="text-xs font-mono font-semibold hover:underline"
                                                style="color:{primary}"
                                            >
                                                {mov.transaction.transaction_number}
                                            </a>
                                        {:else}
                                            <span class="text-xs text-slate-400">-</span>
                                        {/if}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-xs text-slate-500">{mov.notes ?? '-'}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-xs text-slate-500">{fmtDate(mov.created_at)}</span>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                {#if movements.last_page > 1}
                    <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100">
                        <p class="text-xs text-slate-500">
                            Menampilkan {movements.from}–{movements.to} dari {movements.total} data
                        </p>
                        <div class="flex gap-1">
                            {#if movements.prev_page_url}
                                <a href={movements.prev_page_url} class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold hover:bg-slate-50 transition">
                                    <i class="ti ti-chevron-left"></i>
                                </a>
                            {/if}
                            {#if movements.next_page_url}
                                <a href={movements.next_page_url} class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold hover:bg-slate-50 transition">
                                    <i class="ti ti-chevron-right"></i>
                                </a>
                            {/if}
                        </div>
                    </div>
                {/if}
            {/if}
        </div>
    </div>
</AdminLayout>
