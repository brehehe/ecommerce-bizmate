<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';

    let { returns, statusLabels = {}, filters = {}, storeName = '', storeLogo = '' } = $props();

    const primary = $derived((page.props as any).theme?.primary_color ?? '#0c4cb4');
    const secondary = $derived((page.props as any).theme?.secondary_color ?? '#fa7315');

    // svelte-ignore state_referenced_locally
    let filterStatus = $state((filters as any).status ?? '');
    // svelte-ignore state_referenced_locally
    let filterType = $state((filters as any).type ?? '');
    // svelte-ignore state_referenced_locally
    let filterSearch = $state((filters as any).search ?? '');

    let toastMsg = $state('');
    let toastType = $state<'success' | 'error'>('success');
    let toastVisible = $state(false);
    let toastTimer: ReturnType<typeof setTimeout> | null = null;

    function showToast(msg: string, type: 'success' | 'error' = 'success') {
        toastMsg = msg;
        toastType = type;
        toastVisible = true;
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { toastVisible = false; }, 3500);
    }

    function applyFilters() {
        router.get('/admin/returns', {
            status: filterStatus || undefined,
            type: filterType || undefined,
            search: filterSearch || undefined,
        }, { preserveScroll: true });
    }

    function resetFilters() {
        filterStatus = '';
        filterType = '';
        filterSearch = '';
        router.get('/admin/returns');
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
        menunggu_review: { bg: '#fef3c7', text: '#92400e' },
        disetujui: { bg: '#dbeafe', text: '#1e40af' },
        ditolak: { bg: '#fee2e2', text: '#991b1b' },
        barang_dikirim_customer: { bg: '#ffedd5', text: '#9a3412' },
        barang_diterima_toko: { bg: '#ede9fe', text: '#5b21b6' },
        refund_diproses: { bg: '#cffafe', text: '#0e7490' },
        selesai: { bg: '#dcfce7', text: '#166534' },
    };
</script>

<style>
    .scrollbar-none::-webkit-scrollbar { display: none; }
    .scrollbar-none { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<AdminLayout>
    <div class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="font-outfit font-black text-2xl text-slate-800">Pengajuan Retur</h3>
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1">Kelola semua pengajuan retur dari customer</p>
                </div>
            </div>

            <!-- Status Tabs -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-card p-2 overflow-x-auto scrollbar-none flex gap-2">
                <button
                    onclick={() => { filterStatus = ''; applyFilters(); }}
                    class="px-4 py-2.5 rounded-2xl text-xs font-bold transition whitespace-nowrap flex items-center gap-2 border {filterStatus === '' ? 'bg-slate-900 border-slate-900 text-white shadow-sm' : 'bg-transparent border-slate-100 hover:bg-slate-50 text-slate-600'}"
                >
                    <i class="ti ti-layout-grid text-sm"></i>
                    Semua
                </button>
                {#each Object.entries(statusLabels) as [key, label]}
                    {@const color = statusColors[key] ?? { bg: '#f1f5f9', text: '#475569' }}
                    {@const isActive = filterStatus === key}
                    <button
                        onclick={() => { filterStatus = key; applyFilters(); }}
                        class="px-4 py-2.5 rounded-2xl text-xs font-bold transition whitespace-nowrap flex items-center gap-2 border"
                        style={isActive
                            ? `background: ${color.bg}; border-color: ${color.bg}; color: ${color.text};`
                            : `background: transparent; border-color: #f1f5f9; color: #475569;`}
                    >
                        {label as string}
                    </button>
                {/each}
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-card p-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Search -->
                    <div>
                        <p class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5">Cari Retur</p>
                        <div class="relative">
                            <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input
                                type="text"
                                bind:value={filterSearch}
                                placeholder="No. retur / nama customer..."
                                class="w-full pl-8 pr-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition"
                                onkeydown={(e: any) => e.key === 'Enter' && applyFilters()}
                            />
                        </div>
                    </div>

                    <!-- Type -->
                    <div>
                        <p class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 font-outfit">Jenis Retur</p>
                        <select
                            bind:value={filterType}
                            class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition"
                        >
                            <option value="">Semua Jenis</option>
                            <option value="refund">Pengembalian Dana</option>
                            <option value="penggantian_barang">Penggantian Barang</option>
                        </select>
                    </div>

                    <!-- Action -->
                    <div class="flex items-end gap-2">
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
            </div>

            <!-- Table -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden">
                {#if returns.data.length === 0}
                    <div class="flex flex-col items-center justify-center py-16 text-center">
                        <i class="ti ti-arrow-back-up text-5xl text-slate-200 mb-3"></i>
                        <p class="text-slate-500 font-semibold">Tidak ada pengajuan retur</p>
                    </div>
                {:else}
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">
                                    <th class="py-5 px-4">No. Retur</th>
                                    <th class="py-5 px-4">Customer</th>
                                    <th class="py-5 px-4">Transaksi</th>
                                    <th class="py-5 px-4">Jenis</th>
                                    <th class="py-5 px-4">Nominal</th>
                                    <th class="py-5 px-4">Items</th>
                                    <th class="py-5 px-4">Status</th>
                                    <th class="py-5 px-4">Tanggal</th>
                                    <th class="py-5 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700 text-sm font-medium">
                                {#each returns.data as ret (ret.id)}
                                    {@const statusStyle = statusColors[ret.status] ?? { bg: '#f1f5f9', text: '#475569' }}
                                    <tr class="hover:bg-slate-50/50 transition duration-150 border-b border-slate-100">
                                        <td class="py-5 px-4">
                                            <p class="font-bold text-slate-800 font-mono text-xs">{ret.return_number}</p>
                                        </td>
                                        <td class="py-5 px-4">
                                            <p class="font-bold text-slate-800 text-sm">{ret.user?.name ?? '-'}</p>
                                            <p class="text-[11px] text-slate-400 font-bold">{ret.user?.email ?? ''}</p>
                                        </td>
                                        <td class="py-5 px-4">
                                            <p class="font-mono text-xs font-bold text-slate-700">{ret.transaction?.transaction_number ?? '-'}</p>
                                            <p class="text-[11px] text-slate-400 font-bold mt-0.5">{fmt(ret.transaction?.grand_total)}</p>
                                        </td>
                                        <td class="py-5 px-4">
                                            {#if ret.type === 'refund'}
                                                <span class="inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-1 rounded-lg bg-cyan-50 text-cyan-700 border border-cyan-200/50">
                                                    <i class="ti ti-cash-banknote text-xs"></i>
                                                    Pengembalian Dana
                                                </span>
                                            {:else}
                                                <span class="inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-1 rounded-lg bg-purple-50 text-purple-700 border border-purple-200/50">
                                                    <i class="ti ti-package-import text-xs"></i>
                                                    Penggantian Barang
                                                </span>
                                            {/if}
                                        </td>
                                        <td class="py-5 px-4">
                                            <span class="font-black text-slate-800 text-sm">{fmt(ret.refund_amount)}</span>
                                        </td>
                                        <td class="py-5 px-4">
                                            <span class="text-slate-600 font-bold">{(ret.items ?? []).length} item</span>
                                        </td>
                                        <td class="py-5 px-4">
                                            <span
                                                class="text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-wider"
                                                style="background:{statusStyle.bg}; color:{statusStyle.text}"
                                            >
                                                {statusLabels[ret.status] ?? ret.status}
                                            </span>
                                        </td>
                                        <td class="py-5 px-4">
                                            <span class="text-xs text-slate-500 font-bold">{fmtDate(ret.created_at)}</span>
                                        </td>
                                        <td class="py-5 px-4 text-center">
                                            <a
                                                href="/admin/returns/{ret.id}"
                                                class="inline-flex items-center gap-1 text-xs font-bold px-3 py-2 rounded-xl text-white transition font-outfit uppercase tracking-wider"
                                                style="background:{primary};"
                                                title="Detail Retur"
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
                    {#if returns.last_page > 1}
                        <div class="flex items-center justify-between px-6 py-5 border-t border-slate-100 bg-slate-50/20">
                            <p class="text-xs font-bold text-slate-500 font-outfit">
                                Menampilkan {returns.from}–{returns.to} dari {returns.total} retur
                            </p>
                            <div class="flex gap-1">
                                {#if returns.prev_page_url}
                                    <a aria-label="Previous Page" href={returns.prev_page_url} class="w-8 h-8 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 flex items-center justify-center transition">
                                        <i class="ti ti-chevron-left"></i>
                                    </a>
                                {/if}
                                {#if returns.next_page_url}
                                    <a aria-label="Next Page" href={returns.next_page_url} class="w-8 h-8 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 flex items-center justify-center transition">
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

<!-- Toast -->
{#if toastVisible}
    <div
        class="fixed bottom-6 right-6 z-[100] flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-2xl text-white text-sm font-bold"
        style="background:{toastType === 'success' ? '#22c55e' : '#ef4444'};"
    >
        <i class="ti {toastType === 'success' ? 'ti-circle-check' : 'ti-alert-circle'} text-base"></i>
        {toastMsg}
    </div>
{/if}
