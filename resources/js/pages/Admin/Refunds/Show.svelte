<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';

    let { refund, statusLabels = {}, storeName = '', storeLogo = '' } = $props();

    const primary = $derived((page.props as any).theme?.primary_color ?? '#0c4cb4');
    const secondary = $derived((page.props as any).theme?.secondary_color ?? '#fa7315');

    // Modals
    let showApproveModal = $state(false);
    let showRejectModal = $state(false);
    let showCompleteModal = $state(false);

    // svelte-ignore state_referenced_locally
    let adminNotes = $state(refund.notes_admin ?? '');
    let rejectReason = $state('');
    let saving = $state(false);

    let toastMsg = $state('');
    let toastType = $state<'success' | 'error'>('success');
    let toastVisible = $state(false);
    let toastTimer: ReturnType<typeof setTimeout> | null = null;

    function showToast(msg: string, type: 'success' | 'error' = 'success') {
        toastMsg = msg; toastType = type; toastVisible = true;
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { toastVisible = false; }, 4000);
    }

    function fmt(price: any): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency', currency: 'IDR', minimumFractionDigits: 0,
        }).format(Number(price) || 0);
    }

    function fmtDate(dateStr: string | null): string {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit',
        });
    }

    const refundStatusColors: Record<string, { bg: string; text: string }> = {
        menunggu_konfirmasi: { bg: '#fef3c7', text: '#92400e' },
        disetujui: { bg: '#dbeafe', text: '#1e40af' },
        ditolak: { bg: '#fee2e2', text: '#991b1b' },
        selesai: { bg: '#dcfce7', text: '#166534' },
    };

    const statusColor = $derived(refundStatusColors[refund.status] ?? { bg: '#f1f5f9', text: '#475569' });

    function doApprove() {
        saving = true;
        router.post(`/admin/refunds/${refund.id}/approve`, { notes_admin: adminNotes }, {
            onSuccess: () => { 
                showApproveModal = false; 
                showToast('Pengajuan pembatalan disetujui!', 'success'); 
            },
            onError: (e: any) => { 
                showToast(Object.values(e)[0] as string ?? 'Gagal menyetujui pengajuan.', 'error'); 
            },
            onFinish: () => { saving = false; },
        });
    }

    function doReject() {
        if (!rejectReason.trim()) { 
            showToast('Alasan penolakan wajib diisi.', 'error'); 
            return; 
        }
        saving = true;
        router.post(`/admin/refunds/${refund.id}/reject`, { notes_admin: rejectReason }, {
            onSuccess: () => { 
                showRejectModal = false; 
                showToast('Pengajuan pembatalan ditolak.', 'success'); 
            },
            onError: (e: any) => { 
                showToast(Object.values(e)[0] as string ?? 'Gagal menolak pengajuan.', 'error'); 
            },
            onFinish: () => { saving = false; },
        });
    }

    function doCompleteRefund() {
        saving = true;
        router.post(`/admin/refunds/${refund.id}/complete`, {}, {
            onSuccess: () => { 
                showCompleteModal = false;
                showToast('Refund transfer bank berhasil diselesaikan!', 'success'); 
            },
            onError: (e: any) => { 
                showToast(Object.values(e)[0] as string ?? 'Gagal menyelesaikan refund.', 'error'); 
            },
            onFinish: () => { saving = false; },
        });
    }
</script>

<AdminLayout>
    <div class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto">
        <!-- Back + Header -->
        <div class="flex items-start gap-4 mb-6">
            <Link href="/admin/refunds" class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 transition shrink-0">
                <i class="ti ti-arrow-left text-lg"></i>
            </Link>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    <h3 class="font-outfit font-black text-2xl text-slate-800">Refund #{refund.refund_number}</h3>
                    <span
                        class="text-[10px] font-black px-3 py-1.5 rounded-full uppercase tracking-wider"
                        style="background:{statusColor.bg}; color:{statusColor.text}"
                    >
                        {statusLabels[refund.status] ?? refund.status}
                    </span>
                    <span class="text-[10px] font-black px-2.5 py-1 rounded-full bg-cyan-50 text-cyan-700 border border-cyan-200">
                        {#if refund.refund_method === 'poin'}
                            💰 Koin Toko
                        {:else}
                            🏦 Transfer Bank
                        {/if}
                    </span>
                </div>
                <p class="text-xs text-slate-400 font-bold mt-1">Diajukan: {fmtDate(refund.created_at)}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- LEFT: Main Info -->
            <div class="lg:col-span-2 space-y-5">
                <!-- Customer Info -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                    <h4 class="font-bold text-slate-800 text-sm mb-4 flex items-center gap-2">
                        <i class="ti ti-user text-base text-slate-400"></i> Informasi Customer
                    </h4>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm shrink-0" style="background:{primary}">
                            {(refund.user?.name ?? 'C').substring(0, 2).toUpperCase()}
                        </div>
                        <div>
                            <p class="font-bold text-slate-800">{refund.user?.name ?? '-'}</p>
                            <p class="text-xs text-slate-400">{refund.user?.email ?? ''}</p>
                        </div>
                    </div>

                    <!-- Linked transaction -->
                    <div class="p-3 bg-slate-50 rounded-xl">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Transaksi Asal</p>
                        <Link
                            href={`/admin/transactions/${refund.transaction_id}`}
                            class="font-mono font-bold text-sm hover:underline"
                            style="color:{primary}"
                        >
                            {refund.transaction?.transaction_number}
                        </Link>
                        <p class="text-xs text-slate-500 mt-0.5">Grand Total: <strong>{fmt(refund.transaction?.grand_total)}</strong></p>
                    </div>
                </div>

                <!-- Cancellation Reason -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                    <h4 class="font-bold text-slate-800 text-sm mb-3 flex items-center gap-2">
                        <i class="ti ti-message-dots text-base text-slate-400"></i> Alasan Pembatalan
                    </h4>
                    <p class="text-sm text-slate-700 bg-slate-50 rounded-xl p-4 leading-relaxed whitespace-pre-line">{refund.reason}</p>

                    {#if refund.notes_admin}
                        <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-xl">
                            <p class="text-[10px] font-bold text-amber-600 uppercase tracking-wider mb-1">Catatan Admin</p>
                            <p class="text-sm text-amber-800 whitespace-pre-line">{refund.notes_admin}</p>
                        </div>
                    {/if}
                </div>

                <!-- Items Preview -->
                {#if refund.transaction && refund.transaction.items}
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                        <h4 class="font-bold text-slate-800 text-sm mb-4 flex items-center gap-2">
                            <i class="ti ti-package text-base text-slate-400"></i> Produk Pesanan
                        </h4>
                        <div class="space-y-3">
                            {#each refund.transaction.items as item (item.id)}
                                <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl">
                                    <div class="w-10 h-10 rounded-lg bg-slate-200 flex items-center justify-center shrink-0">
                                        <i class="ti ti-package text-slate-400"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-bold text-slate-800 leading-tight truncate">{item.product_name}</p>
                                        {#if item.variant_name}
                                            <span class="text-[10px] font-bold text-slate-505 bg-white border border-slate-200 px-1.5 py-0.5 rounded mt-0.5 inline-block">{item.variant_name}</span>
                                        {/if}
                                    </div>
                                    <div class="text-right shrink-0">
                                        <p class="text-xs font-bold text-slate-500">x{item.quantity}</p>
                                        <p class="text-sm font-black text-slate-850">{fmt(item.harga_akhir ?? item.harga_jual)}</p>
                                    </div>
                                </div>
                            {/each}
                        </div>
                        <div class="flex justify-between items-center pt-3 border-t border-slate-100 mt-3">
                            <span class="text-sm font-bold text-slate-500">Total Nominal Refund</span>
                            <span class="text-lg font-black" style="color:{primary}">{fmt(refund.refund_amount)}</span>
                        </div>
                    </div>
                {/if}
            </div>

            <!-- RIGHT: Actions & Recipient details -->
            <div class="space-y-5">
                <!-- Refund Destination Details -->
                <div class="bg-gradient-to-br from-cyan-50 to-blue-50 rounded-2xl border border-cyan-250 p-5 space-y-3">
                    <h4 class="font-bold text-cyan-850 text-sm flex items-center gap-2">
                        <i class="ti ti-wallet text-base"></i> Detail Pengembalian Dana
                    </h4>
                    
                    {#if refund.refund_method === 'transfer'}
                        <div class="bg-white rounded-xl p-4 border border-cyan-200 space-y-3">
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Bank Penerima</p>
                                <p class="font-black text-slate-800 text-base mt-0.5">{refund.bank_name}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nomor Rekening</p>
                                <p class="font-mono font-bold text-lg text-slate-900 mt-0.5 tracking-wider">{refund.account_number}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Atas Nama</p>
                                <p class="font-bold text-slate-800 text-sm mt-0.5">{refund.account_name}</p>
                            </div>
                        </div>
                    {:else}
                        <div class="bg-white rounded-xl p-4 border border-cyan-200">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Metode Refund</p>
                            <p class="font-black text-slate-800 text-base mt-0.5">Koin Toko (Loyalty Points)</p>
                            <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                                Refund akan dikreditkan secara instan ke saldo koin akun customer setelah pengajuan disetujui.
                            </p>
                        </div>
                    {/if}

                    <div class="bg-white rounded-xl p-3 border border-cyan-200 flex justify-between items-center">
                        <span class="text-xs text-slate-500 font-bold">Jumlah Refund:</span>
                        <span class="text-lg font-black" style="color:{primary}">{fmt(refund.refund_amount)}</span>
                    </div>
                </div>

                <!-- Admin Action Buttons -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-3">
                    <h4 class="font-bold text-slate-800 text-sm mb-2 flex items-center gap-2">
                        <i class="ti ti-settings text-base text-slate-400"></i> Aksi Admin
                    </h4>

                    {#if refund.status === 'menunggu_konfirmasi'}
                        <button
                            onclick={() => (showApproveModal = true)}
                            class="w-full py-3 rounded-xl text-sm font-bold text-white transition active:scale-95 flex items-center justify-center gap-2"
                            style="background:#22c55e"
                        >
                            <i class="ti ti-circle-check text-base"></i> Setujui & Proses Refund
                        </button>
                        <button
                            onclick={() => (showRejectModal = true)}
                            class="w-full py-3 rounded-xl text-sm font-bold text-white transition active:scale-95 flex items-center justify-center gap-2 bg-red-500 hover:bg-red-600"
                        >
                            <i class="ti ti-circle-x text-base"></i> Tolak Pengajuan
                        </button>
                    {:else}
                        {#if refund.status === 'disetujui' && refund.refund_method === 'transfer'}
                            <div class="p-3 bg-blue-50 border border-blue-200 rounded-xl text-xs text-blue-900 leading-relaxed font-medium mb-2">
                                <p class="font-bold flex items-center gap-1"><i class="ti ti-info-circle text-base"></i> Butuh Transfer Manual</p>
                                <p class="mt-1">Silakan lakukan transfer sebesar <strong>{fmt(refund.refund_amount)}</strong> ke rekening di atas secara manual, lalu klik tombol konfirmasi di bawah ini.</p>
                            </div>
                            <button
                                onclick={() => (showCompleteModal = true)}
                                class="w-full py-3 rounded-xl text-sm font-bold text-white transition active:scale-95 flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-600"
                            >
                                <i class="ti ti-circle-check text-base"></i>
                                Konfirmasi Selesai Transfer
                            </button>
                        {:else}
                            <div class="text-center py-4">
                                <i class="ti {refund.status === 'selesai' ? 'ti-circle-check text-emerald-400' : 'ti-circle-x text-red-400'} text-4xl"></i>
                                <p class="text-sm font-bold text-slate-600 mt-2">
                                    {refund.status === 'selesai' ? 'Refund telah selesai.' : 'Refund ditolak.'}
                                </p>
                            </div>
                        {/if}
                    {/if}
                </div>

                <!-- Timeline -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                    <h4 class="font-bold text-slate-800 text-sm mb-4 flex items-center gap-2">
                        <i class="ti ti-timeline text-base text-slate-400"></i> Timeline Pengajuan
                    </h4>
                    <div class="space-y-3 text-xs">
                        <div class="flex gap-3">
                            <div class="w-2 h-2 rounded-full bg-slate-400 shrink-0 mt-1"></div>
                            <div>
                                <p class="font-bold text-slate-750">Pengajuan Diajukan Customer</p>
                                <p class="text-slate-400">{fmtDate(refund.created_at)}</p>
                            </div>
                        </div>
                        {#if refund.processed_at}
                            <div class="flex gap-3">
                                <div class="w-2 h-2 rounded-full shrink-0 mt-1 {refund.status === 'ditolak' ? 'bg-red-400' : 'bg-blue-400'}"></div>
                                <div>
                                    <p class="font-bold {refund.status === 'ditolak' ? 'text-red-700' : 'text-blue-700'}">
                                        {refund.status === 'ditolak' ? 'Ditolak' : 'Disetujui'} oleh {refund.processed_by_user?.name ?? 'Admin'}
                                    </p>
                                    <p class="text-slate-400">{fmtDate(refund.processed_at)}</p>
                                </div>
                            </div>
                        {/if}
                        {#if refund.refunded_at}
                            <div class="flex gap-3">
                                <div class="w-2 h-2 rounded-full bg-emerald-400 shrink-0 mt-1"></div>
                                <div>
                                    <p class="font-bold text-emerald-700">Dana Refund Berhasil Diselesaikan</p>
                                    <p class="text-slate-400">{fmtDate(refund.refunded_at)}</p>
                                </div>
                            </div>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>
</AdminLayout>

<!-- Approve Modal -->
{#if showApproveModal}
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" onclick={() => (showApproveModal = false)}>
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <div class="relative z-10 bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" onclick={(e: any) => e.stopPropagation()}>
            <h3 class="font-bold text-slate-800 text-lg mb-2">Setujui Pengajuan Pembatalan</h3>
            <p class="text-xs text-slate-500 mb-4 leading-normal">
                Pesanan akan dibatalkan, stok produk dikembalikan ke gudang otomatis. Refund akan diproses menggunakan metode yang dipilih customer.
            </p>
            <p class="block text-xs font-bold text-slate-600 mb-2">Catatan untuk Customer (opsional)</p>
            <textarea
                bind:value={adminNotes}
                rows="3"
                placeholder="Tulis pesan/konfirmasi kepada customer..."
                class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none resize-none mb-5"
            ></textarea>
            <div class="flex gap-3">
                <button onclick={() => (showApproveModal = false)} class="flex-1 py-3 rounded-xl border border-slate-200 text-slate-650 font-bold text-sm hover:bg-slate-50">Batal</button>
                <button onclick={doApprove} disabled={saving} class="flex-1 py-3 rounded-xl text-white font-bold text-sm bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 flex items-center justify-center gap-1.5">
                    {#if saving}
                        <div class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
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
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" onclick={() => (showRejectModal = false)}>
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <div class="relative z-10 bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" onclick={(e: any) => e.stopPropagation()}>
            <h3 class="font-bold text-slate-800 text-lg mb-4">Tolak Pengajuan Pembatalan</h3>
            <p class="block text-xs font-bold text-slate-600 mb-2">Alasan Penolakan <span class="text-red-500">*</span></p>
            <textarea
                bind:value={rejectReason}
                rows="3"
                placeholder="Tulis alasan penolakan untuk dikirim ke customer..."
                class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none resize-none mb-5"
            ></textarea>
            <div class="flex gap-3">
                <button onclick={() => (showRejectModal = false)} class="flex-1 py-3 rounded-xl border border-slate-200 text-slate-655 font-bold text-sm hover:bg-slate-50">Batal</button>
                <button onclick={doReject} disabled={saving} class="flex-1 py-3 rounded-xl text-white font-bold text-sm bg-red-550 hover:bg-red-650 disabled:opacity-50 flex items-center justify-center gap-1.5">
                    {#if saving}
                        <div class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
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
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" onclick={() => (showCompleteModal = false)}>
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <div class="relative z-10 bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" onclick={(e: any) => e.stopPropagation()}>
            <h3 class="font-bold text-slate-800 text-lg mb-2">Konfirmasi Selesai Transfer</h3>
            <p class="text-xs text-slate-500 mb-5 leading-normal">
                Apakah Anda yakin dana refund sebesar <strong>{fmt(refund.refund_amount)}</strong> telah ditransfer ke rekening customer? Status pengajuan refund ini akan diubah menjadi Selesai.
            </p>
            <div class="flex gap-3">
                <button onclick={() => (showCompleteModal = false)} class="flex-1 py-3 rounded-xl border border-slate-200 text-slate-650 font-bold text-sm hover:bg-slate-50">Batal</button>
                <button onclick={doCompleteRefund} disabled={saving} class="flex-1 py-3 rounded-xl text-white font-bold text-sm bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 flex items-center justify-center gap-1.5">
                    {#if saving}
                        <div class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                    {/if}
                    Ya, Selesai Transfer
                </button>
            </div>
        </div>
    </div>
{/if}

<!-- Toast -->
{#if toastVisible}
    <div
        class="fixed bottom-6 right-6 z-[200] flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-2xl text-white text-sm font-bold"
        style="background:{toastType === 'success' ? '#22c55e' : '#ef4444'};"
    >
        <i class="ti {toastType === 'success' ? 'ti-circle-check' : 'ti-alert-circle'} text-base"></i>
        {toastMsg}
    </div>
{/if}
