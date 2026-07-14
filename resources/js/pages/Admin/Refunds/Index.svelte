<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import { dragScroll } from '@/utils/dragScroll';
    import Pagination from '@/components/ui/Pagination.svelte';
    import { showToast } from '@/utils/toast';

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

    const refundStatusIcons: Record<string, string> = {
        menunggu_konfirmasi: 'ti-help-circle',
        disetujui: 'ti-circle-check',
        ditolak: 'ti-circle-x',
        selesai: 'ti-check',
    };

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
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-5">
        <!-- Page header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-slate-900">Refund & Pembatalan</h1>
                <p class="mt-0.5 text-sm text-slate-500">Kelola semua pengajuan pembatalan transaksi dan refund dana</p>
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
                {@const icon = refundStatusIcons[key] ?? 'ti-circle'}
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
                    placeholder="No. refund / no. transaksi / customer..."
                    bind:value={filterSearch}
                    onkeydown={(e: any) => e.key === 'Enter' && applyFilters()}
                    class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-8 pr-3 text-sm text-slate-700 placeholder-slate-400 focus:border-slate-400 focus:outline-none focus:ring-0 transition-colors"
                />
            </div>

            <!-- Method -->
            <div class="relative">
                <select
                    bind:value={filterMethod}
                    class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors cursor-pointer"
                >
                    <option value="">Semua Metode</option>
                    <option value="transfer">Transfer Bank</option>
                    <option value="poin">Koin Toko</option>
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
                    <span class="font-semibold">{selectedIds.length}</span> pengajuan refund/pembatalan dipilih
                </span>
                <div class="flex items-center gap-2 ml-auto flex-wrap">
                    {#if countPendingConfirm > 0}
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
                            Setujui Massal ({countPendingConfirm})
                        </button>
                    {/if}

                    {#if countApprovedTransfer > 0}
                        <button
                            onclick={submitBulkComplete}
                            disabled={submittingBulkComplete}
                            class="h-8 rounded-lg px-3 text-xs font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50 flex items-center gap-1.5"
                            style="background-color: {secondary};"
                        >
                            <i class="ti ti-circle-check text-sm"></i>
                            Selesaikan Transfer Massal ({countApprovedTransfer})
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
                    {#if refunds.total !== undefined}
                        <span class="font-semibold text-slate-800">{refunds.total}</span> pengajuan refund
                    {/if}
                </p>
                <div class="flex items-center gap-2">
                    {#if refunds.data.length > 0 && selectableRefunds.length > 0}
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

            {#if refunds.data.length === 0}
                <div class="flex flex-col items-center justify-center py-16 text-center px-4">
                    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                        <i class="ti ti-receipt-refund text-xl"></i>
                    </div>
                    <p class="text-sm font-medium text-slate-600">Tidak ada pengajuan refund</p>
                    <p class="mt-1 text-xs text-slate-400">Coba ubah filter atau kata kunci pencarian</p>
                </div>
            {:else}
                <div class="overflow-x-auto" use:dragScroll>
                    <table class="w-full text-left border-collapse responsive-table">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/50">
                                <th class="w-10 px-4 py-2.5"></th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">No. Refund</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Customer</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Transaksi Asal</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Metode</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Nominal</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Status</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Tanggal</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 text-sm font-medium">
                            {#each refunds.data as ref (ref.id)}
                                {@const statusStyle = refundStatusColors[ref.status] ?? { bg: '#f1f5f9', text: '#475569' }}
                                <tr class="hover:bg-slate-50/50 transition duration-150 border-b border-slate-100">
                                    <td class="py-5 px-4 w-12 text-center">
                                        {#if ref.status === 'menunggu_konfirmasi' || (ref.status === 'disetujui' && ref.refund_method === 'transfer')}
                                            <input
                                                type="checkbox"
                                                checked={selectedIds.includes(ref.id)}
                                                onchange={() => toggleSelect(ref.id)}
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
                                    <td class="px-4 py-3" data-label="No. Refund">
                                        <p class="font-bold text-slate-800 font-mono text-xs">
                                            {ref.refund_number}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3" data-label="Customer">
                                        <div>
                                            <p class="font-bold text-slate-800 text-sm">
                                                {ref.user?.name ?? '-'}
                                            </p>
                                            <p class="text-[11px] text-slate-400 font-bold">
                                                {ref.user?.email ?? ''}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3" data-label="Transaksi Asal">
                                        <div>
                                            <p class="font-mono text-xs font-bold text-slate-700">
                                                {ref.transaction?.transaction_number ?? '-'}
                                            </p>
                                            <span class="text-[10px] text-slate-400 font-bold">Status: {ref.transaction?.status}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3" data-label="Metode">
                                        {#if ref.refund_method === 'poin'}
                                            <span class="inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 border border-amber-200/50">
                                                <i class="ti ti-coins text-xs"></i>
                                                Koin Toko
                                            </span>
                                        {:else}
                                            <span class="inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 border border-blue-200/50">
                                                <i class="ti ti-building-bank text-xs"></i>
                                                Transfer Bank
                                            </span>
                                        {/if}
                                    </td>
                                    <td class="px-4 py-3" data-label="Nominal">
                                        <span class="font-black text-slate-800 text-sm">{fmt(ref.refund_amount)}</span>
                                    </td>
                                    <td class="px-4 py-3" data-label="Status">
                                        <span class="text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-wider" style="background:{statusStyle.bg}; color:{statusStyle.text}">
                                            {statusLabels[ref.status] ?? ref.status}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3" data-label="Tanggal">
                                        <span class="text-xs text-slate-500 font-bold">{fmtDate(ref.created_at)}</span>
                                    </td>
                                    <td class="px-4 py-3" data-label="Aksi">
                                        <Link
                                            href={`/admin/refunds/${ref.id}`}
                                            class="inline-flex items-center gap-1 text-xs font-bold px-3 py-2 rounded-xl text-white transition font-outfit uppercase tracking-wider"
                                            style="background:{primary};"
                                            title="Detail Refund"
                                        >
                                            <i class="ti ti-eye text-sm"></i>
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
                    <Pagination data={refunds} params={{ status: filterStatus, search: filterSearch, refund_method: filterMethod }} />
                {/if}
            {/if}
        </div>
    </main>
</AdminLayout>


<style>
    .scrollbar-none::-webkit-scrollbar {
        display: none;
    }
    .scrollbar-none {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>
