<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import { dragScroll } from '@/utils/dragScroll';
    import Pagination from '@/components/ui/Pagination.svelte';

    let { movements, filters = {}, storeName = '', storeLogo = '' } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#0c4cb4',
    );

    // svelte-ignore state_referenced_locally
    let filterType = $state((filters as any).type ?? '');
    // svelte-ignore state_referenced_locally
    let filterDateFrom = $state((filters as any).date_from ?? '');
    // svelte-ignore state_referenced_locally
    let filterDateTo = $state((filters as any).date_to ?? '');
    // svelte-ignore state_referenced_locally
    let filterPerPage = $state((filters as any).per_page ?? 25);

    function applyFilters() {
        router.get(
            '/admin/stock-movements',
            {
                type: filterType || undefined,
                date_from: filterDateFrom || undefined,
                date_to: filterDateTo || undefined,
                per_page: filterPerPage || undefined,
            },
            { preserveScroll: true },
        );
    }

    function resetFilters() {
        filterType = '';
        filterDateFrom = '';
        filterDateTo = '';
        filterPerPage = 25;
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

    const typeColors: Record<
        string,
        { bg: string; text: string; icon: string }
    > = {
        masuk: { bg: '#dcfce7', text: '#166534', icon: 'ti-arrow-bar-to-up' },
        keluar: {
            bg: '#fee2e2',
            text: '#991b1b',
            icon: 'ti-arrow-bar-to-down',
        },
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
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-5">
        <!-- Page header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-slate-900">Pergerakan Stok</h1>
                <p class="mt-0.5 text-sm text-slate-500">Riwayat pergerakan stok masuk, keluar, retur, dan penyesuaian produk</p>
            </div>
        </div>

        <!-- Filters bar -->
        <div class="flex flex-wrap items-end gap-3">
            <!-- Type -->
            <div class="relative">
                <select
                    bind:value={filterType}
                    class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors cursor-pointer"
                >
                    <option value="">Semua Tipe</option>
                    {#each Object.entries(typeLabels) as [key, label]}
                        <option value={key}>{label}</option>
                    {/each}
                </select>
            </div>

            <!-- Date From -->
            <div class="relative flex flex-col gap-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Dari Tanggal</span>
                <input
                    type="date"
                    bind:value={filterDateFrom}
                    class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors"
                />
            </div>

            <!-- Date To -->
            <div class="relative flex flex-col gap-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Sampai Tanggal</span>
                <input
                    type="date"
                    bind:value={filterDateTo}
                    class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors"
                />
            </div>

            <!-- Per Page -->
            <div class="relative flex flex-col gap-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Per Halaman</span>
                <select
                    bind:value={filterPerPage}
                    class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors cursor-pointer"
                >
                    <option value={10}>10</option>
                    <option value={25}>25</option>
                    <option value={50}>50</option>
                    <option value={100}>100</option>
                </select>
            </div>

            <button
                onclick={applyFilters}
                class="h-9 rounded-lg px-4 text-sm font-semibold text-white transition-opacity hover:opacity-90 cursor-pointer"
                style="background-color: {primary};"
            >
                Filter
            </button>
            <button
                onclick={resetFilters}
                class="h-9 rounded-lg border border-slate-200 px-4 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 cursor-pointer"
            >
                Reset
            </button>
        </div>

        <!-- Table container -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <!-- Table toolbar -->
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                <p class="text-sm text-slate-500">
                    {#if movements.total !== undefined}
                        Menampilkan <span class="font-semibold text-slate-800">{movements.from}–{movements.to}</span> dari <span class="font-semibold text-slate-800">{movements.total}</span> data
                    {/if}
                </p>
            </div>

            {#if movements.data.length === 0}
                <div class="flex flex-col items-center justify-center py-16 text-center px-4">
                    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                        <i class="ti ti-package-off text-xl"></i>
                    </div>
                    <p class="text-sm font-medium text-slate-600">Tidak ada data pergerakan stok</p>
                    <p class="mt-1 text-xs text-slate-400">Coba ubah filter atau tanggal pencarian</p>
                </div>
            {:else}
                <div class="overflow-x-auto" use:dragScroll>
                    <table class="w-full text-left border-collapse responsive-table">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/50">
                                <th class="px-6 py-3.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Produk</th>
                                <th class="px-6 py-3.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Tipe</th>
                                <th class="px-6 py-3.5 text-center text-[10px] font-semibold uppercase tracking-wider text-slate-400">Qty</th>
                                <th class="px-6 py-3.5 text-center text-[10px] font-semibold uppercase tracking-wider text-slate-400">Sebelum</th>
                                <th class="px-6 py-3.5 text-center text-[10px] font-semibold uppercase tracking-wider text-slate-400">Sesudah</th>
                                <th class="px-6 py-3.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Transaksi</th>
                                <th class="px-6 py-3.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Keterangan</th>
                                <th class="px-6 py-3.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 text-sm font-medium">
                            {#each movements.data as mov}
                                {@const typeStyle = typeColors[mov.type] ?? { bg: '#f1f5f9', text: '#475569', icon: 'ti-circle' }}
                                <tr class="hover:bg-slate-50/50 transition duration-150 border-b border-slate-100">
                                    <td class="px-6 py-3.5" data-label="Produk">
                                        <div>
                                            <p class="font-bold text-slate-800 text-sm whitespace-pre-wrap break-words">
                                                {mov.product?.name ?? '-'}
                                            </p>
                                            {#if mov.productVariant}
                                                <p class="text-[11px] text-slate-400 font-bold mt-0.5 break-all">
                                                    SKU: {mov.productVariant.sku}
                                                </p>
                                            {:else if mov.product?.sku}
                                                <p class="text-[11px] text-slate-400 font-bold mt-0.5 break-all">
                                                    SKU: {mov.product.sku}
                                                </p>
                                            {/if}
                                        </div>
                                    </td>
                                    <td class="px-6 py-3.5" data-label="Tipe">
                                        <span
                                            class="inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-wider"
                                            style="background:{typeStyle.bg}; color:{typeStyle.text}"
                                        >
                                            <i class="ti {typeStyle.icon} text-xs"></i>
                                            {typeLabels[mov.type] ?? mov.type}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5 text-center font-black" data-label="Qty">
                                        <span
                                            class="text-sm font-black"
                                            style="color:{mov.quantity > 0 ? '#166534' : '#991b1b'}"
                                        >
                                            {mov.quantity > 0 ? '+' : ''}{mov.quantity}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5 text-center" data-label="Sebelum">
                                        <span class="text-sm font-bold text-slate-500">{mov.stock_before}</span>
                                    </td>
                                    <td class="px-6 py-3.5 text-center" data-label="Sesudah">
                                        <span class="text-sm font-bold text-slate-500">{mov.stock_after}</span>
                                    </td>
                                    <td class="px-6 py-3.5" data-label="Transaksi">
                                        {#if mov.transaction}
                                            <Link
                                                href={`/admin/transactions/${mov.transaction.id}`}
                                                class="text-xs font-mono font-bold hover:underline break-all"
                                                style="color:{primary}"
                                            >
                                                {mov.transaction.transaction_number}
                                            </Link>
                                        {:else}
                                            <span class="text-xs text-slate-400 font-bold">-</span>
                                        {/if}
                                    </td>
                                    <td class="px-6 py-3.5" data-label="Keterangan">
                                        <span class="text-xs text-slate-500 font-semibold whitespace-pre-wrap break-words">
                                            {mov.notes ?? '-'}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5" data-label="Tanggal">
                                        <span class="text-xs text-slate-500 font-bold">{fmtDate(mov.created_at)}</span>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            {/if}
            {#if movements.last_page > 1}
                <Pagination paginator={movements} itemLabel="data" />
            {/if}
        </div>
    </main>
</AdminLayout>
