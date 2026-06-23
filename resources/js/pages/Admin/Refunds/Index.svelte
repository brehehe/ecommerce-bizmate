<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import { dragScroll } from '@/utils/dragScroll';

    let {
        refunds,
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
    let filterMethod = $state((filters as any).refund_method ?? '');
    // svelte-ignore state_referenced_locally
    let filterSearch = $state((filters as any).search ?? '');

    function applyFilters() {
        router.get(
            '/admin/refunds',
            {
                status: filterStatus || undefined,
                refund_method: filterMethod || undefined,
                search: filterSearch || undefined,
            },
            { preserveScroll: true },
        );
    }

    function resetFilters() {
        filterStatus = '';
        filterMethod = '';
        filterSearch = '';
        router.get('/admin/refunds');
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

    const refundStatusColors: Record<string, { bg: string; text: string }> = {
        menunggu_konfirmasi: { bg: '#fef3c7', text: '#92400e' },
        disetujui: { bg: '#dbeafe', text: '#1e40af' },
        ditolak: { bg: '#fee2e2', text: '#991b1b' },
        selesai: { bg: '#dcfce7', text: '#166534' },
    };

    // Toast state
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

    // Selection state
    let selectedIds = $state<number[]>([]);

    const selectableRefunds = $derived(
        (refunds?.data ?? []).filter(
            (r: any) =>
                r.status === 'menunggu_konfirmasi' ||
                (r.status === 'disetujui' && r.refund_method === 'transfer'),
        ),
    );

    const allSelected = $derived(
        selectableRefunds.length > 0 &&
            selectedIds.length === selectableRefunds.length,
    );

    const countPendingConfirm = $derived(
        (refunds?.data ?? []).filter(
            (r: any) =>
                selectedIds.includes(r.id) &&
                r.status === 'menunggu_konfirmasi',
        ).length,
    );

    const countApprovedTransfer = $derived(
        (refunds?.data ?? []).filter(
            (r: any) =>
                selectedIds.includes(r.id) &&
                r.status === 'disetujui' &&
                r.refund_method === 'transfer',
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
            selectedIds = selectableRefunds.map((r: any) => r.id);
        }
    }

    let submittingBulkApprove = $state(false);
    let submittingBulkComplete = $state(false);
    let bulkNotesAdmin = $state('');

    function submitBulkApprove() {
        const pendingConfirmIds = (refunds?.data ?? [])
            .filter(
                (r: any) =>
                    selectedIds.includes(r.id) &&
                    r.status === 'menunggu_konfirmasi',
            )
            .map((r: any) => r.id);

        if (pendingConfirmIds.length === 0) return;

        if (
            !confirm(
                `Apakah Anda yakin ingin menyetujui ${pendingConfirmIds.length} pengajuan pembatalan secara massal?`,
            )
        ) {
            return;
        }

        submittingBulkApprove = true;
        router.post(
            '/admin/refunds/bulk-approve',
            {
                ids: pendingConfirmIds,
                notes_admin: bulkNotesAdmin.trim() || null,
            },
            {
                onSuccess: () => {
                    selectedIds = [];
                    bulkNotesAdmin = '';
                    showToast(
                        'Pengajuan pembatalan berhasil disetujui secara massal.',
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

    function submitBulkComplete() {
        const approvedTransferIds = (refunds?.data ?? [])
            .filter(
                (r: any) =>
                    selectedIds.includes(r.id) &&
                    r.status === 'disetujui' &&
                    r.refund_method === 'transfer',
            )
            .map((r: any) => r.id);

        if (approvedTransferIds.length === 0) return;

        if (
            !confirm(
                `Apakah Anda yakin ingin menyelesaikan transfer refund untuk ${approvedTransferIds.length} transaksi secara massal?`,
            )
        ) {
            return;
        }

        submittingBulkComplete = true;
        router.post(
            '/admin/refunds/bulk-complete',
            {
                ids: approvedTransferIds,
            },
            {
                onSuccess: () => {
                    selectedIds = [];
                    showToast('Transfer refund massal berhasil diselesaikan.');
                },
                onError: (err: any) => {
                    showToast(err.message || 'Terjadi kesalahan.', 'error');
                },
                onFinish: () => {
                    submittingBulkComplete = false;
                },
            },
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
                        Refund & Pembatalan
                    </h3>
                    <p
                        class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                    >
                        Kelola semua pengajuan pembatalan transaksi dan refund
                        dana
                    </p>
                </div>
            </div>

            <!-- Status Tabs -->
            <div
                class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-2 overflow-x-auto scrollbar-none flex gap-2"
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
                    Semua Status
                </button>
                {#each Object.entries(statusLabels) as [key, label]}
                    {@const color = refundStatusColors[key] ?? {
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
                class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6"
            >
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Search -->
                    <div>
                        <p
                            class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5"
                        >
                            Cari Refund
                        </p>
                        <div class="relative">
                            <i
                                class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"
                            ></i>
                            <input
                                type="text"
                                bind:value={filterSearch}
                                placeholder="No. refund / no. transaksi / customer..."
                                class="w-full pl-8 pr-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition"
                                onkeydown={(e: any) =>
                                    e.key === 'Enter' && applyFilters()}
                            />
                        </div>
                    </div>

                    <!-- Method -->
                    <div>
                        <p
                            class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 font-outfit"
                        >
                            Metode Refund
                        </p>
                        <select
                            bind:value={filterMethod}
                            class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition"
                        >
                            <option value="">Semua Metode</option>
                            <option value="transfer">Transfer Bank</option>
                            <option value="poin">Koin Toko</option>
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
                        {selectedIds.length} pengajuan refund/pembatalan dipilih
                    </span>

                    <div class="h-5 w-px bg-slate-200 hidden sm:block"></div>

                    <div class="flex items-center gap-3 flex-wrap">
                        {#if countPendingConfirm > 0}
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
                                    Setujui Massal ({countPendingConfirm})
                                </button>
                            </div>
                        {/if}

                        {#if countApprovedTransfer > 0}
                            <button
                                onclick={submitBulkComplete}
                                disabled={submittingBulkComplete}
                                class="px-4 py-1.5 rounded-xl text-xs font-bold text-white transition disabled:opacity-50 flex items-center gap-1.5"
                                style="background:{secondary}"
                            >
                                <i class="ti ti-circle-check text-sm"></i>
                                Selesaikan Transfer Massal ({countApprovedTransfer})
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
                class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden"
            >
                {#if refunds.data.length === 0}
                    <div
                        class="flex flex-col items-center justify-center py-16 text-center"
                    >
                        <i
                            class="ti ti-receipt-refund text-5xl text-slate-200 mb-3"
                        ></i>
                        <p class="text-slate-500 font-semibold">
                            Tidak ada pengajuan refund
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
                                            disabled={selectableRefunds.length ===
                                                0}
                                        />
                                    </th>
                                    <th class="py-5 px-4">No. Refund</th>
                                    <th class="py-5 px-4">Customer</th>
                                    <th class="py-5 px-4">Transaksi Asal</th>
                                    <th class="py-5 px-4">Metode</th>
                                    <th class="py-5 px-4">Nominal</th>
                                    <th class="py-5 px-4">Status</th>
                                    <th class="py-5 px-4">Tanggal</th>
                                    <th class="py-5 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                            >
                                {#each refunds.data as ref (ref.id)}
                                    {@const statusStyle = refundStatusColors[
                                        ref.status
                                    ] ?? { bg: '#f1f5f9', text: '#475569' }}
                                    <tr
                                        class="hover:bg-slate-50/50 transition duration-150 border-b border-slate-100"
                                    >
                                        <td class="py-5 px-4 w-12 text-center">
                                            {#if ref.status === 'menunggu_konfirmasi' || (ref.status === 'disetujui' && ref.refund_method === 'transfer')}
                                                <input
                                                    type="checkbox"
                                                    checked={selectedIds.includes(
                                                        ref.id,
                                                    )}
                                                    onchange={() =>
                                                        toggleSelect(ref.id)}
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
                                                {ref.refund_number}
                                            </p>
                                        </td>
                                        <td class="py-5 px-4">
                                            <p
                                                class="font-bold text-slate-800 text-sm"
                                            >
                                                {ref.user?.name ?? '-'}
                                            </p>
                                            <p
                                                class="text-[11px] text-slate-400 font-bold"
                                            >
                                                {ref.user?.email ?? ''}
                                            </p>
                                        </td>
                                        <td class="py-5 px-4">
                                            <p
                                                class="font-mono text-xs font-bold text-slate-700"
                                            >
                                                {ref.transaction
                                                    ?.transaction_number ?? '-'}
                                            </p>
                                            <span
                                                class="text-[10px] text-slate-400 font-bold"
                                                >Status: {ref.transaction
                                                    ?.status}</span
                                            >
                                        </td>
                                        <td class="py-5 px-4">
                                            {#if ref.refund_method === 'poin'}
                                                <span
                                                    class="inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 border border-amber-200/50"
                                                >
                                                    <i
                                                        class="ti ti-coins text-xs"
                                                    ></i>
                                                    Koin Toko
                                                </span>
                                            {:else}
                                                <span
                                                    class="inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 border border-blue-200/50"
                                                >
                                                    <i
                                                        class="ti ti-building-bank text-xs"
                                                    ></i>
                                                    Transfer Bank
                                                </span>
                                            {/if}
                                        </td>
                                        <td class="py-5 px-4">
                                            <span
                                                class="font-black text-slate-800 text-sm"
                                                >{fmt(ref.refund_amount)}</span
                                            >
                                        </td>
                                        <td class="py-5 px-4">
                                            <span
                                                class="text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-wider"
                                                style="background:{statusStyle.bg}; color:{statusStyle.text}"
                                            >
                                                {statusLabels[ref.status] ??
                                                    ref.status}
                                            </span>
                                        </td>
                                        <td class="py-5 px-4">
                                            <span
                                                class="text-xs text-slate-500 font-bold"
                                                >{fmtDate(ref.created_at)}</span
                                            >
                                        </td>
                                        <td class="py-5 px-4 text-center">
                                            <Link
                                                href={`/admin/refunds/${ref.id}`}
                                                class="inline-flex items-center gap-1 text-xs font-bold px-3 py-2 rounded-xl text-white transition font-outfit uppercase tracking-wider"
                                                style="background:{primary};"
                                                title="Detail Refund"
                                            >
                                                <i class="ti ti-eye text-sm"
                                                ></i>
                                                Detail
                                            </Link>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    {#if refunds.last_page > 1}
                        <div
                            class="flex items-center justify-between px-6 py-5 border-t border-slate-100 bg-slate-50/20"
                        >
                            <p
                                class="text-xs font-bold text-slate-500 font-outfit"
                            >
                                Menampilkan {refunds.from}–{refunds.to} dari {refunds.total}
                                refund
                            </p>
                            <div class="flex gap-1">
                                {#if refunds.prev_page_url}
                                    <Link
                                        href={refunds.prev_page_url}
                                        class="w-8 h-8 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 flex items-center justify-center transition"
                                    >
                                        <i class="ti ti-chevron-left"></i>
                                    </Link>
                                {/if}
                                {#if refunds.next_page_url}
                                    <Link
                                        href={refunds.next_page_url}
                                        class="w-8 h-8 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 flex items-center justify-center transition"
                                    >
                                        <i class="ti ti-chevron-right"></i>
                                    </Link>
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
