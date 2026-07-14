<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';

    let {
        refund,
        statusLabels = {},
        storeName = '',
        storeLogo = '',
    } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#0c4cb4',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#fa7315',
    );

    // Modals
    let showApproveModal = $state(false);
    let showRejectModal = $state(false);
    let showCompleteModal = $state(false);

    // svelte-ignore state_referenced_locally
    let adminNotes = $state(refund.notes_admin ?? '');
    let rejectReason = $state('');
    let saving = $state(false);



    function fmt(price: any): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Number(price) || 0);
    }

    function fmtDate(dateStr: string | null): string {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
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

    const statusColor = $derived(
        refundStatusColors[refund.status] ?? { bg: '#f1f5f9', text: '#475569' },
    );

    function doApprove() {
        saving = true;
        router.post(
            `/admin/refunds/${refund.id}/approve`,
            { notes_admin: adminNotes },
            {
                onSuccess: () => {
                    showApproveModal = false;
                },
                onError: (e: any) => {
                    showToast(
                        (Object.values(e)[0] as string) ??
                            'Gagal menyetujui pengajuan.',
                        'error',
                    );
                },
                onFinish: () => {
                    saving = false;
                },
            },
        );
    }

    function doReject() {
        if (!rejectReason.trim()) {
            showToast('Alasan penolakan wajib diisi.', 'error');
            return;
        }
        saving = true;
        router.post(
            `/admin/refunds/${refund.id}/reject`,
            { notes_admin: rejectReason },
            {
                onSuccess: () => {
                    showRejectModal = false;
                },
                onError: (e: any) => {
                    showToast(
                        (Object.values(e)[0] as string) ??
                            'Gagal menolak pengajuan.',
                        'error',
                    );
                },
                onFinish: () => {
                    saving = false;
                },
            },
        );
    }

    function doCompleteRefund() {
        saving = true;
        router.post(
            `/admin/refunds/${refund.id}/complete`,
            {},
            {
                onSuccess: () => {
                    showCompleteModal = false;
                },
                onError: (e: any) => {
                    showToast(
                        (Object.values(e)[0] as string) ??
                            'Gagal menyelesaikan refund.',
                        'error',
                    );
                },
                onFinish: () => {
                    saving = false;
                },
            },
        );
    }
</script>

<svelte:head>
    <title>Refund #{refund.refund_number} — Admin</title>
</svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1400px] mx-auto px-4 sm:px-6 py-6">
        <!-- Page header -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <!-- Left: back + title -->
            <div class="flex items-center gap-3">
                <Link
                    href="/admin/refunds"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-800"
                    aria-label="Kembali"
                >
                    <i class="ti ti-arrow-left text-sm"></i>
                </Link>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="font-mono text-lg font-bold tracking-tight text-slate-900">
                            #{refund.refund_number}
                        </h1>
                        <span
                            class="rounded-md px-2 py-0.5 text-[11px] font-semibold"
                            style="background-color: {statusColor.bg}; color: {statusColor.text};"
                        >
                            {statusLabels[refund.status] ?? refund.status}
                        </span>
                        <span
                            class="rounded-md px-2 py-0.5 text-[11px] font-semibold bg-cyan-50 text-cyan-700 border border-cyan-100"
                        >
                            {#if refund.refund_method === 'poin'}
                                💰 Koin Toko
                            {:else}
                                🏦 Transfer Bank
                            {/if}
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-400 font-medium">Diajukan: {fmtDate(refund.created_at)}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <!-- Main: left 2 cols -->
            <div class="space-y-5 lg:col-span-2">
                <!-- Customer Info -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <p class="text-sm font-semibold text-slate-800">Informasi Customer</p>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm shrink-0"
                                style="background:{primary}"
                            >
                                {(refund.user?.name ?? 'C')
                                    .substring(0, 2)
                                    .toUpperCase()}
                            </div>
                            <div>
                                <p class="font-bold text-slate-800 text-sm">
                                    {refund.user?.name ?? '-'}
                                </p>
                                <p class="text-xs text-slate-400">
                                    {refund.user?.email ?? ''}
                                </p>
                            </div>
                        </div>

                        <!-- Linked transaction -->
                        <div class="p-3.5 bg-slate-50 rounded-lg">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
                                Transaksi Asal
                            </p>
                            <Link
                                href={`/admin/transactions/${refund.transaction_id}`}
                                class="font-mono font-bold text-sm hover:underline"
                                style="color:{primary}"
                            >
                                {refund.transaction?.transaction_number}
                            </Link>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Grand Total: <strong>{fmt(refund.transaction?.grand_total)}</strong>
                            </p>
                            {#if parseFloat(refund.transaction?.admin_fee) > 0 || parseFloat(refund.transaction?.application_fee) > 0 || (refund.transaction?.additional_costs && refund.transaction.additional_costs.length > 0)}
                                <div class="mt-2 pt-2 border-t border-slate-200/60 space-y-1 text-[11px] text-slate-400">
                                    {#if parseFloat(refund.transaction?.admin_fee) > 0}
                                        <div class="flex justify-between">
                                            <span>Biaya Admin:</span>
                                            <span>{fmt(refund.transaction?.admin_fee)}</span>
                                        </div>
                                    {/if}
                                    {#if parseFloat(refund.transaction?.application_fee) > 0}
                                        <div class="flex justify-between">
                                            <span>Biaya Aplikasi:</span>
                                            <span>{fmt(refund.transaction?.application_fee)}</span>
                                        </div>
                                    {/if}
                                    {#if refund.transaction?.additional_costs && Array.isArray(refund.transaction.additional_costs)}
                                        {#each refund.transaction.additional_costs as cost}
                                            {#if parseFloat(cost.value) > 0}
                                                <div class="flex justify-between">
                                                    <span>{cost.name}:</span>
                                                    <span>{fmt(parseFloat(cost.value))}</span>
                                                </div>
                                            {/if}
                                        {/each}
                                    {/if}
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>

                <!-- Alasan Pembatalan -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <p class="text-sm font-semibold text-slate-800">Alasan Pembatalan</p>
                    </div>
                    <div class="p-5 space-y-3">
                        <p class="text-sm text-slate-700 bg-slate-50 rounded-lg p-4 leading-relaxed whitespace-pre-line">
                            {refund.reason}
                        </p>
                        {#if refund.notes_admin}
                            <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                <p class="text-[10px] font-bold text-amber-600 uppercase tracking-wider mb-1">
                                    Catatan Admin
                                </p>
                                <p class="text-sm text-amber-800 whitespace-pre-line">
                                    {refund.notes_admin}
                                </p>
                            </div>
                        {/if}
                    </div>
                </div>

                <!-- Items Preview -->
                {#if refund.transaction && refund.transaction.items}
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <div class="border-b border-slate-100 px-5 py-3.5">
                            <p class="text-sm font-semibold text-slate-800">Produk Pesanan</p>
                        </div>
                        <div class="divide-y divide-slate-100">
                            {#each refund.transaction.items as item (item.id)}
                                <div class="flex items-center gap-3 p-4 bg-white hover:bg-slate-50/50 transition">
                                    <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center shrink-0 border border-slate-200/50">
                                        <i class="ti ti-package text-slate-400"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-bold text-slate-800 leading-tight truncate">
                                            {item.product_name}
                                        </p>
                                        {#if item.variant_name}
                                            <span class="text-[10px] font-bold text-slate-500 bg-slate-50 border border-slate-200 px-1.5 py-0.5 rounded mt-1 inline-block">
                                                {item.variant_name}
                                            </span>
                                        {/if}
                                    </div>
                                    <div class="text-right shrink-0">
                                        <p class="text-xs font-bold text-slate-500">
                                            x{item.quantity}
                                        </p>
                                        <p class="text-sm font-black text-slate-800">
                                            {fmt(item.harga_akhir ?? item.harga_jual)}
                                        </p>
                                    </div>
                                </div>
                            {/each}
                        </div>
                        <div class="flex justify-between items-center p-5 border-t border-slate-100 bg-slate-50/20">
                            <span class="text-sm font-semibold text-slate-500">Total Nominal Refund</span>
                            <span class="text-lg font-black" style="color:{primary}">{fmt(refund.refund_amount)}</span>
                        </div>
                    </div>
                {/if}
            </div>

            <!-- RIGHT: Actions & Recipient details -->
            <div class="space-y-5">
                <!-- Refund Destination Details -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-5 py-3.5 bg-gradient-to-br from-cyan-50 to-blue-50/30">
                        <p class="text-sm font-semibold text-slate-800">Detail Pengembalian Dana</p>
                    </div>
                    <div class="p-5 space-y-4">
                        {#if refund.refund_method === 'transfer'}
                            <div class="space-y-3">
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                        Bank Penerima
                                    </p>
                                    <p class="font-black text-slate-800 text-base mt-0.5">
                                        {refund.bank_name}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                        Nomor Rekening
                                    </p>
                                    <p class="font-mono font-bold text-lg text-slate-900 mt-0.5 tracking-wider">
                                        {refund.account_number}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                        Atas Nama
                                    </p>
                                    <p class="font-bold text-slate-800 text-sm mt-0.5">
                                        {refund.account_name}
                                    </p>
                                </div>
                            </div>
                        {:else}
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                    Metode Refund
                                </p>
                                <p class="font-black text-slate-800 text-base mt-0.5">
                                    Koin Toko (Loyalty Points)
                                </p>
                                <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                                    Refund akan dikreditkan secara instan ke saldo koin akun customer setelah pengajuan disetujui.
                                </p>
                            </div>
                        {/if}

                        <div class="p-3 bg-cyan-50/50 rounded-lg border border-cyan-100 flex justify-between items-center">
                            <span class="text-xs text-slate-500 font-bold">Jumlah Refund:</span>
                            <span class="text-lg font-black" style="color:{primary}">{fmt(refund.refund_amount)}</span>
                        </div>
                    </div>
                </div>

                <!-- Admin Action Buttons -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <p class="text-sm font-semibold text-slate-800">Aksi Admin</p>
                    </div>
                    <div class="p-5 space-y-3">
                        {#if refund.status === 'menunggu_konfirmasi'}
                            <button
                                onclick={() => (showApproveModal = true)}
                                class="w-full py-2.5 rounded-lg text-xs font-semibold text-white transition-opacity hover:opacity-90 active:scale-[0.98] flex items-center justify-center gap-2"
                                style="background:#22c55e"
                            >
                                <i class="ti ti-circle-check text-base"></i> Setujui & Proses Refund
                            </button>
                            <button
                                onclick={() => (showRejectModal = true)}
                                class="w-full py-2.5 rounded-lg text-xs font-semibold text-white transition-opacity hover:opacity-90 active:scale-[0.98] flex items-center justify-center gap-2 bg-rose-500"
                            >
                                <i class="ti ti-circle-x text-base"></i> Tolak Pengajuan
                            </button>
                        {:else if refund.status === 'disetujui' && refund.refund_method === 'transfer'}
                            <div class="p-3 bg-blue-50 border border-blue-100 rounded-lg text-xs text-blue-900 leading-relaxed font-medium mb-1">
                                <p class="font-bold flex items-center gap-1">
                                    <i class="ti ti-info-circle text-base"></i> Butuh Transfer Manual
                                </p>
                                <p class="mt-1">
                                    Silakan lakukan transfer sebesar <strong>{fmt(refund.refund_amount)}</strong> ke rekening di atas secara manual, lalu klik tombol konfirmasi di bawah ini.
                                </p>
                            </div>
                            <button
                                onclick={() => (showCompleteModal = true)}
                                class="w-full py-2.5 rounded-lg text-xs font-semibold text-white transition-opacity hover:opacity-90 active:scale-[0.98] flex items-center justify-center gap-2 bg-emerald-500"
                            >
                                <i class="ti ti-circle-check text-base"></i> Konfirmasi Selesai Transfer
                            </button>
                        {:else}
                            <div class="text-center py-4">
                                <i class="ti {refund.status === 'selesai' ? 'ti-circle-check text-emerald-500' : 'ti-circle-x text-rose-500'} text-4xl"></i>
                                <p class="text-sm font-semibold text-slate-600 mt-2">
                                    {refund.status === 'selesai' ? 'Refund telah selesai.' : 'Refund ditolak.'}
                                </p>
                            </div>
                        {/if}
                    </div>
                </div>

                <!-- Timeline -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <p class="text-sm font-semibold text-slate-800">Timeline Pengajuan</p>
                    </div>
                    <div class="p-5 space-y-4 text-xs">
                        <div class="flex gap-3">
                            <div class="w-2 h-2 rounded-full bg-slate-400 shrink-0 mt-1.5"></div>
                            <div>
                                <p class="font-bold text-slate-700">
                                    Pengajuan Diajukan Customer
                                </p>
                                <p class="text-slate-400 mt-0.5">
                                    {fmtDate(refund.created_at)}
                                </p>
                            </div>
                        </div>
                        {#if refund.processed_at}
                            <div class="flex gap-3 border-t border-slate-50 pt-3">
                                <div class="w-2 h-2 rounded-full shrink-0 mt-1.5 {refund.status === 'ditolak' ? 'bg-rose-400' : 'bg-blue-400'}"></div>
                                <div>
                                    <p class="font-bold {refund.status === 'ditolak' ? 'text-rose-700' : 'text-blue-700'}">
                                        {refund.status === 'ditolak' ? 'Ditolak' : 'Disetujui'} oleh {refund.processed_by_user?.name ?? 'Admin'}
                                    </p>
                                    <p class="text-slate-400 mt-0.5">
                                        {fmtDate(refund.processed_at)}
                                    </p>
                                </div>
                            </div>
                        {/if}
                        {#if refund.refunded_at}
                            <div class="flex gap-3 border-t border-slate-50 pt-3">
                                <div class="w-2 h-2 rounded-full bg-emerald-550 shrink-0 mt-1.5"></div>
                                <div>
                                    <p class="font-bold text-emerald-700">
                                        Dana Refund Berhasil Diselesaikan
                                    </p>
                                    <p class="text-slate-400 mt-0.5">
                                        {fmtDate(refund.refunded_at)}
                                    </p>
                                </div>
                            </div>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </main>
</AdminLayout>

<!-- Approve Modal -->
{#if showApproveModal}
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        onclick={() => (showApproveModal = false)}
    >
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <div
            class="relative z-10 bg-white rounded-2xl shadow-2xl w-full max-w-md p-6"
            onclick={(e: any) => e.stopPropagation()}
        >
            <h3 class="font-bold text-slate-800 text-lg mb-2">
                Setujui Pengajuan Pembatalan
            </h3>
            <p class="text-xs text-slate-500 mb-4 leading-normal">
                Pesanan akan dibatalkan, stok produk dikembalikan ke gudang
                otomatis. Refund akan diproses menggunakan metode yang dipilih
                customer.
            </p>
            <p class="block text-xs font-bold text-slate-600 mb-2">
                Catatan untuk Customer (opsional)
            </p>
            <textarea
                bind:value={adminNotes}
                rows="3"
                placeholder="Tulis pesan/konfirmasi kepada customer..."
                class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none resize-none mb-5"
            ></textarea>
            <div class="flex gap-3">
                <button
                    onclick={() => (showApproveModal = false)}
                    class="flex-1 py-3 rounded-xl border border-slate-200 text-slate-650 font-bold text-sm hover:bg-slate-50"
                    >Batal</button
                >
                <button
                    onclick={doApprove}
                    disabled={saving}
                    class="flex-1 py-3 rounded-xl text-white font-bold text-sm bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 flex items-center justify-center gap-1.5"
                >
                    {#if saving}
                        <div
                            class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"
                        ></div>
                    {/if}
                    Ya, Setujui
                </button>
            </div>
        </div>
    </div>
{/if}

<!-- Reject Modal -->
{#if showRejectModal}
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        onclick={() => (showRejectModal = false)}
    >
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <div
            class="relative z-10 bg-white rounded-2xl shadow-2xl w-full max-w-md p-6"
            onclick={(e: any) => e.stopPropagation()}
        >
            <h3 class="font-bold text-slate-800 text-lg mb-4">
                Tolak Pengajuan Pembatalan
            </h3>
            <p class="block text-xs font-bold text-slate-600 mb-2">
                Alasan Penolakan <span class="text-red-500">*</span>
            </p>
            <textarea
                bind:value={rejectReason}
                rows="3"
                placeholder="Tulis alasan penolakan untuk dikirim ke customer..."
                class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none resize-none mb-5"
            ></textarea>
            <div class="flex gap-3">
                <button
                    onclick={() => (showRejectModal = false)}
                    class="flex-1 py-3 rounded-xl border border-slate-200 text-slate-655 font-bold text-sm hover:bg-slate-50"
                    >Batal</button
                >
                <button
                    onclick={doReject}
                    disabled={saving}
                    class="flex-1 py-3 rounded-xl text-white font-bold text-sm bg-red-550 hover:bg-red-650 disabled:opacity-50 flex items-center justify-center gap-1.5"
                >
                    {#if saving}
                        <div
                            class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"
                        ></div>
                    {/if}
                    Ya, Tolak
                </button>
            </div>
        </div>
    </div>
{/if}

<!-- Complete Transfer Modal -->
{#if showCompleteModal}
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        onclick={() => (showCompleteModal = false)}
    >
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <div
            class="relative z-10 bg-white rounded-2xl shadow-2xl w-full max-w-md p-6"
            onclick={(e: any) => e.stopPropagation()}
        >
            <h3 class="font-bold text-slate-800 text-lg mb-2">
                Konfirmasi Selesai Transfer
            </h3>
            <p class="text-xs text-slate-500 mb-5 leading-normal">
                Apakah Anda yakin dana refund sebesar <strong
                    >{fmt(refund.refund_amount)}</strong
                > telah ditransfer ke rekening customer? Status pengajuan refund ini
                akan diubah menjadi Selesai.
            </p>
            <div class="flex gap-3">
                <button
                    onclick={() => (showCompleteModal = false)}
                    class="flex-1 py-3 rounded-xl border border-slate-200 text-slate-650 font-bold text-sm hover:bg-slate-50"
                    >Batal</button
                >
                <button
                    onclick={doCompleteRefund}
                    disabled={saving}
                    class="flex-1 py-3 rounded-xl text-white font-bold text-sm bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 flex items-center justify-center gap-1.5"
                >
                    {#if saving}
                        <div
                            class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"
                        ></div>
                    {/if}
                    Ya, Selesai Transfer
                </button>
            </div>
        </div>
    </div>
{/if}

