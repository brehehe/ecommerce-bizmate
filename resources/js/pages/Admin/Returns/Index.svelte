<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';
    import { dragScroll } from '@/utils/dragScroll';

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

    // Selection state
    let selectedIds = $state<number[]>([]);

    const selectableReturns = $derived(
        (returns?.data ?? []).filter(
            (r: any) => r.status === 'menunggu_review' || r.status === 'barang_dikirim_customer'
        )
    );

    const allSelected = $derived(
        selectableReturns.length > 0 &&
            selectedIds.length === selectableReturns.length
    );

    const countPendingReview = $derived(
        (returns?.data ?? []).filter(
            (r: any) => selectedIds.includes(r.id) && r.status === 'menunggu_review'
        ).length
    );

    const countShipped = $derived(
        (returns?.data ?? []).filter(
            (r: any) => selectedIds.includes(r.id) && r.status === 'barang_dikirim_customer'
        ).length
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
            .filter((r: any) => selectedIds.includes(r.id) && r.status === 'menunggu_review')
            .map((r: any) => r.id);

        if (pendingReviewIds.length === 0) return;

        if (!confirm(`Apakah Anda yakin ingin menyetujui ${pendingReviewIds.length} pengajuan retur secara massal?`)) {
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
                    showToast('Pengajuan retur berhasil disetujui secara massal.');
                },
                onError: (err: any) => {
                    showToast(err.message || 'Terjadi kesalahan.', 'error');
                },
                onFinish: () => {
                    submittingBulkApprove = false;
                },
            }
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
            .filter((r: any) => selectedIds.includes(r.id) && r.status === 'barang_dikirim_customer')
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
                    showToast('Konfirmasi penerimaan barang berhasil diproses secara massal.');
                },
                onError: (err: any) => {
                    showToast(err.message || 'Terjadi kesalahan.', 'error');
                },
                onFinish: () => {
                    submittingBulkReceipt = false;
                },
            }
        );
    }
</script>

<AdminLayout>
    <div class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto">
        <div class="space-y-6">
            <!-- Header -->
            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
            >
                <div>
                    <h3 class="font-outfit font-black text-2xl text-slate-800">
                        Pengajuan Retur
                    </h3>
                    <p
                        class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                    >
                        Kelola semua pengajuan retur dari customer
                    </p>
                </div>
            </div>

            <!-- Status Tabs -->
            <div
                class="bg-white rounded-3xl border border-slate-200/80 shadow-card p-2 overflow-x-auto scrollbar-none flex gap-2"
            >
                <button
                    onclick={() => {
                        filterStatus = '';
                        applyFilters();
                    }}
                    class="px-4 py-2.5 rounded-2xl text-xs font-bold transition whitespace-nowrap flex items-center gap-2 border {filterStatus ===
                    ''
                        ? 'bg-slate-900 border-slate-900 text-white shadow-sm'
                        : 'bg-transparent border-slate-100 hover:bg-slate-50 text-slate-600'}"
                >
                    <i class="ti ti-layout-grid text-sm"></i>
                    Semua
                </button>
                {#each Object.entries(statusLabels) as [key, label]}
                    {@const color = statusColors[key] ?? {
                        bg: '#f1f5f9',
                        text: '#475569',
                    }}
                    {@const isActive = filterStatus === key}
                    <button
                        onclick={() => {
                            filterStatus = key;
                            applyFilters();
                        }}
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
            <div
                class="bg-white rounded-3xl border border-slate-200/80 shadow-card p-6"
            >
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Search -->
                    <div>
                        <p
                            class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5"
                        >
                            Cari Retur
                        </p>
                        <div class="relative">
                            <i
                                class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"
                            ></i>
                            <input
                                type="text"
                                bind:value={filterSearch}
                                placeholder="No. retur / nama customer..."
                                class="w-full pl-8 pr-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition"
                                onkeydown={(e: any) =>
                                    e.key === 'Enter' && applyFilters()}
                            />
                        </div>
                    </div>

                    <!-- Type -->
                    <div>
                        <p
                            class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 font-outfit"
                        >
                            Jenis Retur
                        </p>
                        <select
                            bind:value={filterType}
                            class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition"
                        >
                            <option value="">Semua Jenis</option>
                            <option value="refund">Pengembalian Dana</option>
                            <option value="penggantian_barang"
                                >Penggantian Barang</option
                            >
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

            <!-- Bulk action bar (when items selected) -->
            {#if selectedIds.length > 0}
                <div
                    class="bg-white rounded-2xl border border-slate-200 shadow-md px-5 py-4 flex items-center gap-4 flex-wrap"
                >
                    <span class="text-sm font-bold text-slate-700">
                        <i
                            class="ti ti-checkbox text-base mr-1"
                            style="color:{primary}"
                        ></i>
                        {selectedIds.length} pengajuan retur dipilih
                    </span>

                    <div class="h-5 w-px bg-slate-200 hidden sm:block"></div>

                    <div class="flex items-center gap-3 flex-wrap">
                        {#if countPendingReview > 0}
                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    bind:value={bulkNotesAdmin}
                                    placeholder="Catatan persetujuan massal..."
                                    class="px-3 py-1.5 text-xs border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white w-48 sm:w-64"
                                />
                                <button
                                    onclick={submitBulkApprove}
                                    disabled={submittingBulkApprove}
                                    class="px-4 py-1.5 rounded-xl text-xs font-bold text-white transition disabled:opacity-50 flex items-center gap-1.5"
                                    style="background:{primary}"
                                >
                                    <i class="ti ti-check text-sm"></i>
                                    Setujui Massal ({countPendingReview})
                                </button>
                            </div>
                        {/if}

                        {#if countShipped > 0}
                            <button
                                onclick={openReceiptModal}
                                class="px-4 py-1.5 rounded-xl text-xs font-bold text-white transition hover:bg-opacity-90 flex items-center gap-1.5"
                                style="background:{secondary}"
                            >
                                <i class="ti ti-package-import text-sm"></i>
                                Konfirmasi Penerimaan Massal ({countShipped})
                            </button>
                        {/if}
                    </div>

                    <button
                        onclick={() => {
                            selectedIds = [];
                            bulkNotesAdmin = '';
                        }}
                        class="text-xs text-slate-400 hover:text-slate-600 font-bold ml-auto"
                    >
                        Batalkan Pilihan
                    </button>
                </div>
            {/if}

            <!-- Table -->
            <div
                class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden"
            >
                {#if returns.data.length === 0}
                    <div
                        class="flex flex-col items-center justify-center py-16 text-center"
                    >
                        <i
                            class="ti ti-arrow-back-up text-5xl text-slate-200 mb-3"
                        ></i>
                        <p class="text-slate-500 font-semibold">
                            Tidak ada pengajuan retur
                        </p>
                    </div>
                {:else}
                    <div class="overflow-x-auto" use:dragScroll>
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr
                                    class="border-b border-slate-100 bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                                >
                                    <th class="py-5 px-4 w-12 text-center">
                                        <input
                                            type="checkbox"
                                            checked={allSelected}
                                            onchange={toggleSelectAll}
                                            class="rounded border-slate-300 text-slate-900 focus:ring-slate-500 w-4 h-4 cursor-pointer"
                                            disabled={selectableReturns.length === 0}
                                        />
                                    </th>
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
                            <tbody
                                class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                            >
                                {#each returns.data as ret (ret.id)}
                                    {@const statusStyle = statusColors[
                                        ret.status
                                    ] ?? { bg: '#f1f5f9', text: '#475569' }}
                                    <tr
                                        class="hover:bg-slate-50/50 transition duration-150 border-b border-slate-100"
                                    >
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
                                        <td class="py-5 px-4">
                                            <p
                                                class="font-bold text-slate-800 font-mono text-xs"
                                            >
                                                {ret.return_number}
                                            </p>
                                        </td>
                                        <td class="py-5 px-4">
                                            <p
                                                class="font-bold text-slate-800 text-sm"
                                            >
                                                {ret.user?.name ?? '-'}
                                            </p>
                                            <p
                                                class="text-[11px] text-slate-400 font-bold"
                                            >
                                                {ret.user?.email ?? ''}
                                            </p>
                                        </td>
                                        <td class="py-5 px-4">
                                            <p
                                                class="font-mono text-xs font-bold text-slate-700"
                                            >
                                                {ret.transaction
                                                    ?.transaction_number ?? '-'}
                                            </p>
                                            <p
                                                class="text-[11px] text-slate-400 font-bold mt-0.5"
                                            >
                                                {fmt(
                                                    ret.transaction
                                                        ?.grand_total,
                                                )}
                                            </p>
                                        </td>
                                        <td class="py-5 px-4">
                                            {#if ret.type === 'refund'}
                                                <span
                                                    class="inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-1 rounded-lg bg-cyan-50 text-cyan-700 border border-cyan-200/50"
                                                >
                                                    <i
                                                        class="ti ti-cash-banknote text-xs"
                                                    ></i>
                                                    Pengembalian Dana
                                                </span>
                                            {:else}
                                                <span
                                                    class="inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-1 rounded-lg bg-purple-50 text-purple-700 border border-purple-200/50"
                                                >
                                                    <i
                                                        class="ti ti-package-import text-xs"
                                                    ></i>
                                                    Penggantian Barang
                                                </span>
                                            {/if}
                                        </td>
                                        <td class="py-5 px-4">
                                            <span
                                                class="font-black text-slate-800 text-sm"
                                                >{fmt(ret.refund_amount)}</span
                                            >
                                        </td>
                                        <td class="py-5 px-4">
                                            <span
                                                class="text-slate-600 font-bold"
                                                >{(ret.items ?? []).length} item</span
                                            >
                                        </td>
                                        <td class="py-5 px-4">
                                            <span
                                                class="text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-wider"
                                                style="background:{statusStyle.bg}; color:{statusStyle.text}"
                                            >
                                                {statusLabels[ret.status] ??
                                                    ret.status}
                                            </span>
                                        </td>
                                        <td class="py-5 px-4">
                                            <span
                                                class="text-xs text-slate-500 font-bold"
                                                >{fmtDate(ret.created_at)}</span
                                            >
                                        </td>
                                        <td class="py-5 px-4 text-center">
                                            <a
                                                href="/admin/returns/{ret.id}"
                                                class="inline-flex items-center gap-1 text-xs font-bold px-3 py-2 rounded-xl text-white transition font-outfit uppercase tracking-wider"
                                                style="background:{primary};"
                                                title="Detail Retur"
                                            >
                                                <i class="ti ti-eye text-sm"
                                                ></i>
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
                        <div
                            class="flex items-center justify-between px-6 py-5 border-t border-slate-100 bg-slate-50/20"
                        >
                            <p
                                class="text-xs font-bold text-slate-500 font-outfit"
                            >
                                Menampilkan {returns.from}–{returns.to} dari {returns.total}
                                retur
                            </p>
                            <div class="flex gap-1">
                                {#if returns.prev_page_url}
                                    <a
                                        aria-label="Previous Page"
                                        href={returns.prev_page_url}
                                        class="w-8 h-8 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 flex items-center justify-center transition"
                                    >
                                        <i class="ti ti-chevron-left"></i>
                                    </a>
                                {/if}
                                {#if returns.next_page_url}
                                    <a
                                        aria-label="Next Page"
                                        href={returns.next_page_url}
                                        class="w-8 h-8 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 flex items-center justify-center transition"
                                    >
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
                    <h3 class="font-outfit font-black text-lg text-slate-800 flex items-center gap-2">
                        <i class="ti ti-package-import text-xl" style="color:{secondary}"></i>
                        Penerimaan Barang Retur Massal
                    </h3>
                    <button
                        onclick={() => showReceiptModal = false}
                        class="text-slate-400 hover:text-slate-600 transition"
                    >
                        <i class="ti ti-x text-lg"></i>
                    </button>
                </div>

                <p class="text-xs text-slate-500 font-bold mb-6 uppercase tracking-wider">
                    Konfirmasi penerimaan barang untuk {countShipped} retur terpilih.
                </p>

                <div class="space-y-4 mb-6">
                    <p class="text-xs font-bold text-slate-700 uppercase tracking-widest font-outfit">
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
                            <p class="text-xs font-black text-slate-800">Kembalikan ke Stok Aktif</p>
                            <p class="text-[11px] text-slate-400 font-medium mt-0.5">
                                Menambah kuantitas produk kembali ke stok aktif yang dapat dijual.
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
                            <p class="text-xs font-black text-slate-800">Tulis Sebagai Rusak</p>
                            <p class="text-[11px] text-slate-400 font-medium mt-0.5">
                                Barang rusak/tidak layak jual. Stok aktif produk tidak akan bertambah.
                            </p>
                        </div>
                    </label>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        onclick={() => showReceiptModal = false}
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
