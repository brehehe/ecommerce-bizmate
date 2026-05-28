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
    const statusIcons: Record<string, string> = {
        belum_bayar: 'ti-wallet',
        menunggu: 'ti-hourglass-low',
        diproses: 'ti-settings',
        dikemas: 'ti-package',
        dikirim: 'ti-truck-delivery',
        selesai: 'ti-circle-check',
        batal: 'ti-circle-x',
    };

    function setStatusFilter(status: string) {
        filterStatus = status;
        applyFilters();
    }
</script>

<style>
    .scrollbar-none::-webkit-scrollbar {
        display: none;
    }
    .scrollbar-none {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>

<AdminLayout>
    <div class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="font-outfit font-black text-2xl text-slate-800">Transaksi</h3>
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1">Kelola semua pesanan customer</p>
                </div>
            </div>

            <!-- Status Tabs -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-card p-2 overflow-x-auto scrollbar-none flex gap-2">
                <button
                    onclick={() => setStatusFilter('')}
                    class="px-4 py-2.5 rounded-2xl text-xs font-bold transition whitespace-nowrap flex items-center gap-2 border {filterStatus === '' ? 'bg-slate-900 border-slate-900 text-white shadow-sm' : 'bg-transparent border-slate-100 hover:bg-slate-50 text-slate-600'}"
                >
                    <i class="ti ti-layout-grid text-sm"></i>
                    Semua Transaksi
                </button>
                {#each Object.entries(statusLabels) as [key, label]}
                    {@const color = statusColors[key] ?? { bg: '#f1f5f9', text: '#475569' }}
                    {@const icon = statusIcons[key] ?? 'ti-circle'}
                    {@const isActive = filterStatus === key}
                    <button
                        onclick={() => setStatusFilter(key)}
                        class="px-4 py-2.5 rounded-2xl text-xs font-bold transition whitespace-nowrap flex items-center gap-2 border"
                        style={isActive 
                            ? `background: ${color.bg}; border-color: ${color.bg}; color: ${color.text}; box-shadow: 0 4px 12px -2px ${color.text}20;` 
                            : `background: transparent; border-color: #f1f5f9; color: #475569;`}
                        onmouseenter={(e) => { if (!isActive) e.currentTarget.style.backgroundColor = '#f8fafc'; }}
                        onmouseleave={(e) => { if (!isActive) e.currentTarget.style.backgroundColor = 'transparent'; }}
                    >
                        <i class="ti {icon} text-sm"></i>
                        {label}
                    </button>
                {/each}
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-card p-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 font-outfit">Cari</label>
                        <div class="relative">
                            <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input
                                type="text"
                                bind:value={filterSearch}
                                placeholder="No. transaksi / nama customer..."
                                class="w-full pl-8 pr-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition"
                                onkeydown={(e: any) => e.key === 'Enter' && applyFilters()}
                            />
                        </div>
                    </div>

                    <!-- Date From -->
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 font-outfit">Dari Tanggal</label>
                        <input
                            type="date"
                            bind:value={filterDateFrom}
                            class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition"
                        />
                    </div>

                    <!-- Date To -->
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 font-outfit">Sampai Tanggal</label>
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
                {#if transactions.data.length === 0}
                    <div class="flex flex-col items-center justify-center py-16 text-center">
                        <i class="ti ti-shopping-cart-off text-5xl text-slate-200 mb-3"></i>
                        <p class="text-slate-500 font-semibold">Tidak ada transaksi</p>
                    </div>
                {:else}
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">
                                    <th class="py-6 px-6">No. Transaksi</th>
                                    <th class="py-6 px-6">Customer</th>
                                    <th class="py-6 px-6">Items</th>
                                    <th class="py-6 px-6">Total</th>
                                    <th class="py-6 px-6">Status</th>
                                    <th class="py-6 px-6">Pembayaran</th>
                                    <th class="py-6 px-6">Tanggal</th>
                                    <th class="py-6 px-6 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700 text-sm font-medium">
                                {#each transactions.data as trx}
                                    {@const statusStyle = statusColors[trx.status] ?? { bg: '#f1f5f9', text: '#475569' }}
                                    {@const paymentStatus = trx.payment?.status}
                                    <tr class="hover:bg-slate-50/50 transition duration-150 border-b border-slate-100">
                                        <td class="py-6 px-6">
                                            <p class="font-bold text-slate-800 font-mono text-xs">{trx.transaction_number}</p>
                                        </td>
                                        <td class="py-6 px-6">
                                            <p class="font-bold text-slate-800">{trx.user?.name ?? '-'}</p>
                                            <p class="text-[11px] text-slate-400 font-bold mt-0.5">{trx.user?.email ?? ''}</p>
                                        </td>
                                        <td class="py-6 px-6">
                                            <span class="text-slate-600 font-bold">{(trx.items ?? []).length} item</span>
                                        </td>
                                        <td class="py-6 px-6">
                                            <span class="font-black text-slate-800">{fmt(trx.grand_total)}</span>
                                        </td>
                                        <td class="py-6 px-6">
                                            <span
                                                class="text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-wider"
                                                style="background:{statusStyle.bg}; color:{statusStyle.text}"
                                            >
                                                {statusLabels[trx.status] ?? trx.status}
                                            </span>
                                        </td>
                                        <td class="py-6 px-6">
                                            {#if paymentStatus === 'confirmed'}
                                                <span class="text-[10px] font-black px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-200/50 uppercase tracking-wider">Dikonfirmasi</span>
                                            {:else if paymentStatus === 'rejected'}
                                                <span class="text-[10px] font-black px-2.5 py-1 rounded-lg bg-rose-50 text-rose-600 border border-rose-200/50 uppercase tracking-wider">Ditolak</span>
                                            {:else if trx.payment?.proof_image}
                                                <span class="text-[10px] font-black px-2.5 py-1 rounded-lg bg-amber-50 text-amber-600 border border-amber-200/50 uppercase tracking-wider">Menunggu Review</span>
                                            {:else}
                                                <span class="text-xs text-slate-400 font-bold">Belum ada bukti</span>
                                            {/if}
                                        </td>
                                        <td class="py-6 px-6">
                                            <span class="text-xs text-slate-500 font-bold">{fmtDate(trx.created_at)}</span>
                                        </td>
                                        <td class="py-6 px-6 text-center">
                                            <a
                                                href="/admin/transactions/{trx.id}"
                                                class="inline-flex items-center gap-1.5 text-xs font-bold px-4 py-2 rounded-xl text-white transition font-outfit uppercase tracking-wider shadow-md hover:shadow-lg"
                                                style="background:{primary}; box-shadow: 0 4px 10px -2px {primary}30;"
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
                        <div class="flex items-center justify-between px-6 py-5 border-t border-slate-100 bg-slate-50/20">
                            <p class="text-xs font-bold text-slate-500 font-outfit">
                                Menampilkan {transactions.from}–{transactions.to} dari {transactions.total} transaksi
                            </p>
                            <div class="flex gap-1">
                                {#if transactions.prev_page_url}
                                    <a href={transactions.prev_page_url} class="w-8 h-8 rounded-lg border border-slate-200 text-slate-500 hover:bg-brand-blueLight hover:text-brand-blueRoyal flex items-center justify-center transition">
                                        <i class="ti ti-chevron-left"></i>
                                    </a>
                                {/if}
                                {#if transactions.next_page_url}
                                    <a href={transactions.next_page_url} class="w-8 h-8 rounded-lg border border-slate-200 text-slate-500 hover:bg-brand-blueLight hover:text-brand-blueRoyal flex items-center justify-center transition">
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
