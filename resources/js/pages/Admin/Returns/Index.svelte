<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import { dragScroll } from '@/utils/dragScroll';
    import Pagination from '@/components/ui/Pagination.svelte';

    let {
        returns,
        statusLabels = {},
        filters = {},
        storeName = '',
        storeLogo = '',
    } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#0c4cb4',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#fa7315',
    );

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
        toastTimer = setTimeout(() => {
            toastVisible = false;
        }, 3500);
    }

    function applyFilters() {
        router.get(
            '/admin/returns',
            {
                status: filterStatus || undefined,
                type: filterType || undefined,
                search: filterSearch || undefined,
            },
            { preserveScroll: true },
        );
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

    const returnStatusIcons: Record<string, string> = {
        menunggu_review: 'ti-help-circle',
        disetujui: 'ti-circle-check',
        ditolak: 'ti-circle-x',
        barang_dikirim_customer: 'ti-truck-delivery',
        barang_diterima_toko: 'ti-package',
        refund_diproses: 'ti-cash-banknote',
        selesai: 'ti-check',
    };

    // Selection state
    let selectedIds = $state<number[]>([]);

    const selectableReturns = $derived(
        (returns?.data ?? []).filter(
            (r: any) =>
                r.status === 'menunggu_review' ||
                r.status === 'barang_dikirim_customer',
        ),
    );

    const allSelected = $derived(
        selectableReturns.length > 0 &&
            selectedIds.length === selectableReturns.length,
    );

    const countPendingReview = $derived(
        (returns?.data ?? []).filter(
            (r: any) =>
                selectedIds.includes(r.id) && r.status === 'menunggu_review',
        ).length,
    );

    const countShipped = $derived(
        (returns?.data ?? []).filter(
            (r: any) =>
                selectedIds.includes(r.id) &&
                r.status === 'barang_dikirim_customer',
        ).length,
    );

    function toggleSelect(id: number) {
        if (selectedIds.includes(id)) {
            selectedIds = selectedIds.filter((x) => x !== id);
        } else {
            selectedIds = [...selectedIds, id];
        }
    }

    function toggleSelectAll() {
        if (allSelected) {
            selectedIds = [];
        } else {
            selectedIds = selectableReturns.map((r: any) => r.id);
        }
    }

    let submittingBulkApprove = $state(false);
    let bulkNotesAdmin = $state('');

    function submitBulkApprove() {
        const pendingReviewIds = (returns?.data ?? [])
            .filter(
                (r: any) =>
                    selectedIds.includes(r.id) &&
                    r.status === 'menunggu_review',
            )
            .map((r: any) => r.id);

        if (pendingReviewIds.length === 0) return;

        if (
            !confirm(
                `Apakah Anda yakin ingin menyetujui ${pendingReviewIds.length} pengajuan retur secara massal?`,
            )
        ) {
            return;
        }

        submittingBulkApprove = true;
        router.post(
            '/admin/returns/bulk-approve',
            {
                ids: pendingReviewIds,
                notes_admin: bulkNotesAdmin.trim() || null,
            },
            {
                onSuccess: () => {
                    selectedIds = [];
                    bulkNotesAdmin = '';
                    showToast(
                        'Pengajuan retur berhasil disetujui secara massal.',
                    );
                },
                onError: (err: any) => {
                    showToast(err.message || 'Terjadi kesalahan.', 'error');
                },
                onFinish: () => {
                    submittingBulkApprove = false;
                },
            },
        );
    }

    // Modal state for bulk confirm receipt
    let showReceiptModal = $state(false);
    let stockAction = $state<'active' | 'damaged'>('active');
    let submittingBulkReceipt = $state(false);

    function openReceiptModal() {
        showReceiptModal = true;
    }

    function submitBulkReceipt() {
        const shippedIds = (returns?.data ?? [])
            .filter(
                (r: any) =>
                    selectedIds.includes(r.id) &&
                    r.status === 'barang_dikirim_customer',
            )
            .map((r: any) => r.id);

        if (shippedIds.length === 0) return;

        submittingBulkReceipt = true;
        router.post(
            '/admin/returns/bulk-confirm-receipt',
            {
                ids: shippedIds,
                stock_action: stockAction,
            },
            {
                onSuccess: () => {
                    selectedIds = [];
                    showReceiptModal = false;
                    showToast(
                        'Konfirmasi penerimaan barang berhasil diproses secara massal.',
                    );
                },
                onError: (err: any) => {
                    showToast(err.message || 'Terjadi kesalahan.', 'error');
                },
                onFinish: () => {
                    submittingBulkReceipt = false;
                },
            },
        );
    }
</script>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-5">
        <!-- Page header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-slate-900">Retur Barang</h1>
                <p class="mt-0.5 text-sm text-slate-500">Kelola semua pengajuan retur barang dan komplain dari pelanggan</p>
            </div>
        </div>

        <!-- Status tabs -->
        <div class="flex gap-1.5 overflow-x-auto border-b border-slate-200 pb-0 scrollbar-none">
            <button
                onclick={() => {
                    filterStatus = '';
                    applyFilters();
                }}
                class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 pb-3 pt-1 text-xs font-semibold transition-colors whitespace-nowrap
                    {filterStatus === '' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'}"
            >
                <i class="ti ti-layout-grid text-sm"></i>
                Semua Status
            </button>
            {#each Object.entries(statusLabels) as [key, label]}
                {@const icon = returnStatusIcons[key] ?? 'ti-circle'}
                {@const isActive = filterStatus === key}
                <button
                    onclick={() => {
                        filterStatus = key;
                        applyFilters();
                    }}
                    class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 pb-3 pt-1 text-xs font-semibold transition-colors whitespace-nowrap
                        {isActive ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'}"
                >
                    <i class="ti {icon} text-sm"></i>
                    {label}
                </button>
            {/each}
        </div>

        <!-- Filters bar -->
        <div class="flex flex-wrap items-end gap-3">
            <!-- Search -->
            <div class="relative flex-1 min-w-48">
                <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                <input
                    type="search"
                    placeholder="No. retur / nama customer..."
                    bind:value={filterSearch}
                    onkeydown={(e: any) => e.key === 'Enter' && applyFilters()}
                    class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-8 pr-3 text-sm text-slate-700 placeholder-slate-400 focus:border-slate-400 focus:outline-none focus:ring-0 transition-colors"
                />
            </div>

            <!-- Type -->
            <div class="relative">
                <select
                    bind:value={filterType}
                    class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors cursor-pointer"
                >
                    <option value="">Semua Jenis</option>
                    <option value="refund">Pengembalian Dana</option>
                    <option value="penggantian_barang">Penggantian Barang</option>
                </select>
            </div>

            <button
                onclick={applyFilters}
                class="h-9 rounded-lg px-4 text-sm font-semibold text-white transition-opacity hover:opacity-90"
                style="background-color: {primary};"
            >
                Filter
            </button>
            <button
                onclick={resetFilters}
                class="h-9 rounded-lg border border-slate-200 px-4 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
            >
                Reset
            </button>
        </div>

        <!-- Bulk action bar -->
        {#if selectedIds.length > 0}
            <div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-2.5 flex-wrap shadow-xs">
                <span class="text-sm font-medium text-slate-700">
                    <span class="font-semibold">{selectedIds.length}</span> pengajuan retur dipilih
                </span>
                <div class="flex items-center gap-2 ml-auto flex-wrap">
                    {#if countPendingReview > 0}
                        <input
                            type="text"
                            bind:value={bulkNotesAdmin}
                            placeholder="Catatan persetujuan massal..."
                            class="h-8 px-3 text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-0 focus:border-slate-400 bg-white w-48 sm:w-64"
                        />
                        <button
                            onclick={submitBulkApprove}
                            disabled={submittingBulkApprove}
                            class="h-8 rounded-lg px-3 text-xs font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50 flex items-center gap-1.5"
                            style="background-color: {primary};"
                        >
                            <i class="ti ti-check text-sm"></i>
                            Setujui Massal ({countPendingReview})
                        </button>
                    {/if}

                    {#if countShipped > 0}
                        <button
                            onclick={openReceiptModal}
                            class="h-8 rounded-lg px-3 text-xs font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50 flex items-center gap-1.5"
                            style="background-color: {secondary};"
                        >
                            <i class="ti ti-package-import text-sm"></i>
                            Konfirmasi Penerimaan Massal ({countShipped})
                        </button>
                    {/if}

                    <button
                        onclick={() => {
                            selectedIds = [];
                            bulkNotesAdmin = '';
                        }}
                        class="h-8 rounded-lg border border-slate-200 px-3 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-50"
                    >
                        Batal
                    </button>
                </div>
            </div>
        {/if}

        <!-- Table container -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <!-- Table toolbar -->
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                <p class="text-sm text-slate-500">
                    {#if returns.total !== undefined}
                        <span class="font-semibold text-slate-800">{returns.total}</span> pengajuan retur
                    {/if}
                </p>
                <div class="flex items-center gap-2">
                    {#if returns.data.length > 0 && selectableReturns.length > 0}
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={allSelected}
                                onchange={toggleSelectAll}
                                class="rounded border-slate-300 accent-slate-900"
                            />
                            <span class="text-xs text-slate-500">Pilih semua</span>
                        </label>
                    {/if}
                </div>
            </div>

            {#if returns.data.length === 0}
                <div class="flex flex-col items-center justify-center py-16 text-center px-4">
                    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                        <i class="ti ti-arrow-back-up text-xl"></i>
                    </div>
                    <p class="text-sm font-medium text-slate-600">Tidak ada pengajuan retur</p>
                    <p class="mt-1 text-xs text-slate-400">Coba ubah filter atau kata kunci pencarian</p>
                </div>
            {:else}
                <div class="overflow-x-auto" use:dragScroll>
                    <table class="w-full text-left border-collapse responsive-table">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/50">
                                <th class="w-10 px-4 py-2.5"></th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">No. Retur</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Customer</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Transaksi</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Jenis</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Nominal</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Items</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Status</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Tanggal</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 text-sm font-medium">
                            {#each returns.data as ret (ret.id)}
                                {@const statusStyle = statusColors[ret.status] ?? { bg: '#f1f5f9', text: '#475569' }}
                                <tr class="hover:bg-slate-50/50 transition duration-150 border-b border-slate-100">
                                    <td class="py-5 px-4 w-12 text-center">
                                        {#if ret.status === 'menunggu_review' || ret.status === 'barang_dikirim_customer'}
                                            <input
                                                type="checkbox"
                                                checked={selectedIds.includes(ret.id)}
                                                onchange={() => toggleSelect(ret.id)}
                                                class="rounded border-slate-300 text-slate-900 focus:ring-slate-500 w-4 h-4 cursor-pointer"
                                            />
                                        {:else}
                                            <input
                                                type="checkbox"
                                                disabled
                                                class="rounded border-slate-200 text-slate-200 w-4 h-4 cursor-not-allowed opacity-30"
                                            />
                                        {/if}
                                    </td>
                                    <td class="px-4 py-3" data-label="No. Retur">
                                        <p class="font-bold text-slate-800 font-mono text-xs">
                                            {ret.return_number}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3" data-label="Customer">
                                        <div>
                                            <p class="font-bold text-slate-800 text-sm">
                                                {ret.user?.name ?? '-'}
                                            </p>
                                            <p class="text-[11px] text-slate-400 font-bold">
                                                {ret.user?.email ?? ''}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3" data-label="Transaksi">
                                        <div>
                                            <p class="font-mono text-xs font-bold text-slate-700">
                                                {ret.transaction?.transaction_number ?? '-'}
                                            </p>
                                            <p class="text-[11px] text-slate-400 font-bold mt-0.5">
                                                {fmt(ret.transaction?.grand_total)}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3" data-label="Jenis">
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
                                    <td class="px-4 py-3" data-label="Nominal">
                                        <span class="font-black text-slate-800 text-sm">{fmt(ret.refund_amount)}</span>
                                    </td>
                                    <td class="px-4 py-3" data-label="Items">
                                        <span class="text-slate-600 font-semibold">{(ret.items ?? []).length} item</span>
                                    </td>
                                    <td class="px-4 py-3" data-label="Status">
                                        <span class="text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-wider" style="background:{statusStyle.bg}; color:{statusStyle.text}">
                                            {statusLabels[ret.status] ?? ret.status}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3" data-label="Tanggal">
                                        <span class="text-xs text-slate-500 font-bold">{fmtDate(ret.created_at)}</span>
                                    </td>
                                    <td class="px-4 py-3" data-label="Aksi">
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
            {/if}
        </div>

        {#if returns.last_page > 1}
            <div class="border-t border-slate-100 px-4 py-2.5">
                <Pagination data={returns} params={{ status: filterStatus, search: filterSearch, type: filterType }} />
            </div>
        {/if}
    </main>
</AdminLayout>

<!-- Bulk Confirm Receipt Modal -->
{#if showReceiptModal}
    <div
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[150] flex items-center justify-center p-4 animate-fade-in"
    >
        <div
            class="bg-white rounded-3xl border border-slate-100 shadow-2xl w-full max-w-md overflow-hidden animate-scale-up"
        >
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3
                        class="font-outfit font-black text-lg text-slate-800 flex items-center gap-2"
                    >
                        <i
                            class="ti ti-package-import text-xl"
                            style="color:{secondary}"
                        ></i>
                        Penerimaan Barang Retur Massal
                    </h3>
                    <button
                        onclick={() => (showReceiptModal = false)}
                        class="text-slate-400 hover:text-slate-600 transition"
                    >
                        <i class="ti ti-x text-lg"></i>
                    </button>
                </div>

                <p
                    class="text-xs text-slate-500 font-bold mb-6 uppercase tracking-wider"
                >
                    Konfirmasi penerimaan barang untuk {countShipped} retur terpilih.
                </p>

                <div class="space-y-4 mb-6">
                    <p
                        class="text-xs font-bold text-slate-700 uppercase tracking-widest font-outfit"
                    >
                        Tindakan Terhadap Stok:
                    </p>
                    <label
                        class="flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition"
                    >
                        <input
                            type="radio"
                            name="stock_action_bulk"
                            value="active"
                            bind:group={stockAction}
                            class="mt-0.5 rounded-full border-slate-300 text-slate-900 focus:ring-slate-500"
                        />
                        <div>
                            <p class="text-xs font-black text-slate-800">
                                Kembalikan ke Stok Aktif
                            </p>
                            <p
                                class="text-[11px] text-slate-400 font-medium mt-0.5"
                            >
                                Menambah kuantitas produk kembali ke stok aktif
                                yang dapat dijual.
                            </p>
                        </div>
                    </label>

                    <label
                        class="flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition"
                    >
                        <input
                            type="radio"
                            name="stock_action_bulk"
                            value="damaged"
                            bind:group={stockAction}
                            class="mt-0.5 rounded-full border-slate-300 text-slate-900 focus:ring-slate-500"
                        />
                        <div>
                            <p class="text-xs font-black text-slate-800">
                                Tulis Sebagai Rusak
                            </p>
                            <p
                                class="text-[11px] text-slate-400 font-medium mt-0.5"
                            >
                                Barang rusak/tidak layak jual. Stok aktif produk
                                tidak akan bertambah.
                            </p>
                        </div>
                    </label>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        onclick={() => (showReceiptModal = false)}
                        class="flex-1 py-3 rounded-2xl text-xs font-bold text-slate-500 border border-slate-200 hover:bg-slate-50 transition uppercase tracking-wider font-outfit"
                    >
                        Batal
                    </button>
                    <button
                        onclick={submitBulkReceipt}
                        disabled={submittingBulkReceipt}
                        class="flex-1 py-3 rounded-2xl text-xs font-bold text-white transition disabled:opacity-50 uppercase tracking-wider font-outfit"
                        style="background:{secondary}"
                    >
                        {submittingBulkReceipt ? 'Memproses...' : 'Konfirmasi'}
                    </button>
                </div>
            </div>
        </div>
    </div>
{/if}

<!-- Toast -->
{#if toastVisible}
    <div
        class="fixed bottom-6 right-6 z-[100] flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-2xl text-white text-sm font-bold"
        style="background:{toastType === 'success' ? '#22c55e' : '#ef4444'};"
    >
        <i
            class="ti {toastType === 'success'
                ? 'ti-circle-check'
                : 'ti-alert-circle'} text-base"
        ></i>
        {toastMsg}
    </div>
{/if}

<style>
    .scrollbar-none::-webkit-scrollbar {
        display: none;
    }
    .scrollbar-none {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>
