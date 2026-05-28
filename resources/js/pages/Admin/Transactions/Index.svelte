<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';

    let { transactions, statusLabels = {}, filters = {}, storeName = '', storeLogo = '' } = $props();

    const primary = $derived((page.props as any).theme?.primary_color ?? '#0c4cb4');
    const secondary = $derived((page.props as any).theme?.secondary_color ?? '#fa7315');

    let filterStatus = $state((filters as any).status ?? '');
    let filterDateFrom = $state((filters as any).date_from ?? '');
    let filterDateTo = $state((filters as any).date_to ?? '');
    let filterSearch = $state((filters as any).search ?? '');

    function applyFilters() {
        router.get('/admin/transactions', {
            status: filterStatus || undefined,
            date_from: filterDateFrom || undefined,
            date_to: filterDateTo || undefined,
            search: filterSearch || undefined,
        }, { preserveScroll: true });
    }

    function resetFilters() {
        filterStatus = '';
        filterDateFrom = '';
        filterDateTo = '';
        filterSearch = '';
        router.get('/admin/transactions');
    }

    function fmt(price: any): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Number(price) || 0);
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

    const statusColors: Record<string, { bg: string; text: string }> = {
        belum_bayar: { bg: '#fef3c7', text: '#92400e' },
        menunggu: { bg: '#dbeafe', text: '#1e40af' },
        diproses: { bg: '#ede9fe', text: '#5b21b6' },
        dikemas: { bg: '#cffafe', text: '#0e7490' },
        dikirim: { bg: '#ffedd5', text: '#9a3412' },
        selesai: { bg: '#dcfce7', text: '#166534' },
        batal: { bg: '#fee2e2', text: '#991b1b' },
    };
</script>

<AdminLayout {storeName} {storeLogo}>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-black text-slate-800">Transaksi</h1>
                <p class="text-sm text-slate-500 mt-1">Kelola semua pesanan customer</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <!-- Search -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Cari</label>
                    <div class="relative">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input
                            type="text"
                            bind:value={filterSearch}
                            placeholder="No. transaksi / nama"
                            class="w-full pl-8 pr-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:border-transparent"
                            onkeydown={(e: any) => e.key === 'Enter' && applyFilters()}
                        />
                    </div>
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                    <select
                        bind:value={filterStatus}
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:border-transparent appearance-none bg-white"
                    >
                        <option value="">Semua Status</option>
                        {#each Object.entries(statusLabels) as [key, label]}
                            <option value={key}>{label as string}</option>
                        {/each}
                    </select>
                </div>

                <!-- Date From -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Dari Tanggal</label>
                    <input
                        type="date"
                        bind:value={filterDateFrom}
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:border-transparent"
                    />
                </div>

                <!-- Date To -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Sampai Tanggal</label>
                    <input
                        type="date"
                        bind:value={filterDateTo}
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:border-transparent"
                    />
                </div>
            </div>

            <div class="flex gap-2 mt-3">
                <button
                    onclick={applyFilters}
                    class="px-4 py-2 rounded-xl text-sm font-bold text-white transition"
                    style="background:{primary}"
                >
                    <i class="ti ti-filter mr-1"></i>Filter
                </button>
                <button
                    onclick={resetFilters}
                    class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 border border-slate-200 hover:bg-slate-50 transition"
                >
                    Reset
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            {#if transactions.data.length === 0}
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <i class="ti ti-shopping-cart-off text-5xl text-slate-200 mb-3"></i>
                    <p class="text-slate-500 font-semibold">Tidak ada transaksi</p>
                </div>
            {:else}
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">No. Transaksi</th>
                                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Customer</th>
                                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Items</th>
                                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Total</th>
                                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Pembayaran</th>
                                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Tanggal</th>
                                <th class="text-right px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            {#each transactions.data as trx}
                                {@const statusStyle = statusColors[trx.status] ?? { bg: '#f1f5f9', text: '#475569' }}
                                {@const paymentStatus = trx.payment?.status}
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-4 py-3">
                                        <p class="font-bold text-slate-800 font-mono text-xs">{trx.transaction_number}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-700">{trx.user?.name ?? '-'}</p>
                                        <p class="text-xs text-slate-400">{trx.user?.email ?? ''}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-slate-600">{(trx.items ?? []).length} item</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="font-bold text-slate-800">{fmt(trx.grand_total)}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="text-[10px] font-black px-2 py-1 rounded-full"
                                            style="background:{statusStyle.bg}; color:{statusStyle.text}"
                                        >
                                            {statusLabels[trx.status] ?? trx.status}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        {#if paymentStatus === 'confirmed'}
                                            <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-green-100 text-green-700">Dikonfirmasi</span>
                                        {:else if paymentStatus === 'rejected'}
                                            <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-red-100 text-red-700">Ditolak</span>
                                        {:else if trx.payment?.proof_image}
                                            <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-amber-100 text-amber-700">Menunggu Review</span>
                                        {:else}
                                            <span class="text-[10px] text-slate-400">Belum ada bukti</span>
                                        {/if}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-xs text-slate-500">{fmtDate(trx.created_at)}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a
                                            href="/admin/transactions/{trx.id}"
                                            class="inline-flex items-center gap-1 text-xs font-bold px-3 py-1.5 rounded-lg text-white transition"
                                            style="background:{primary}"
                                        >
                                            <i class="ti ti-eye text-sm"></i>
                                            Detail
                                        </a>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                {#if transactions.last_page > 1}
                    <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100">
                        <p class="text-xs text-slate-500">
                            Menampilkan {transactions.from}–{transactions.to} dari {transactions.total} transaksi
                        </p>
                        <div class="flex gap-1">
                            {#if transactions.prev_page_url}
                                <a href={transactions.prev_page_url} class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold hover:bg-slate-50 transition">
                                    <i class="ti ti-chevron-left"></i>
                                </a>
                            {/if}
                            {#if transactions.next_page_url}
                                <a href={transactions.next_page_url} class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold hover:bg-slate-50 transition">
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
