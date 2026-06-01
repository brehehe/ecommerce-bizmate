<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';

    let { return: ret, statusLabels = {}, storeName = '', storeLogo = '' } = $props();

    const primary = $derived((page.props as any).theme?.primary_color ?? '#0c4cb4');
    const secondary = $derived((page.props as any).theme?.secondary_color ?? '#fa7315');

    // Modals
    let showApproveModal = $state(false);
    let showRejectModal = $state(false);
    let showReceiptModal = $state(false);
    let showTrackingModal = $state(false);
    let showCustomerTrackingModal = $state(false);
    let showReplacementTrackingModal = $state(false);
    let showMediaViewer = $state(false);
    let viewingMedia: any = $state(null);

    let adminNotes = $state(ret.notes_admin ?? '');
    let rejectReason = $state('');
    let trackingNumber = $state(ret.return_tracking_number ?? '');
    let courierName = $state(ret.return_courier_name ?? '');
    let replacementTracking = $state(ret.replacement_tracking_number ?? '');
    let replacementCourier = $state(ret.replacement_courier_name ?? '');
    let stockAction = $state('active'); // 'active' | 'damaged'
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

    function formatMediaUrl(path: string): string {
        if (!path || path.startsWith('http') || path.startsWith('/')) return path;
        return '/storage/' + path;
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

    const statusColor = $derived(statusColors[ret.status] ?? { bg: '#f1f5f9', text: '#475569' });
    const primaryBankAccount = $derived(
        ret.user?.customer_bank_accounts?.find((a: any) => a.is_primary) ??
        ret.user?.customer_bank_accounts?.[0] ?? null
    );

    function doApprove() {
        saving = true;
        router.post(`/admin/returns/${ret.id}/approve`, { notes_admin: adminNotes }, {
            onSuccess: () => { showApproveModal = false; showToast('Retur disetujui!', 'success'); },
            onError: (e: any) => { showToast(Object.values(e)[0] as string ?? 'Gagal.', 'error'); },
            onFinish: () => { saving = false; },
        });
    }

    function doReject() {
        if (!rejectReason.trim()) { showToast('Alasan penolakan wajib diisi.', 'error'); return; }
        saving = true;
        router.post(`/admin/returns/${ret.id}/reject`, { notes_admin: rejectReason }, {
            onSuccess: () => { showRejectModal = false; showToast('Retur ditolak.', 'success'); },
            onError: (e: any) => { showToast(Object.values(e)[0] as string ?? 'Gagal.', 'error'); },
            onFinish: () => { saving = false; },
        });
    }

    function doConfirmReceipt() {
        saving = true;
        router.post(`/admin/returns/${ret.id}/confirm-receipt`, { stock_action: stockAction }, {
            onSuccess: () => { showReceiptModal = false; showToast('Penerimaan barang dikonfirmasi!', 'success'); },
            onError: (e: any) => { showToast(Object.values(e)[0] as string ?? 'Gagal.', 'error'); },
            onFinish: () => { saving = false; },
        });
    }

    function doProcessRefund() {
        saving = true;
        router.post(`/admin/returns/${ret.id}/process-refund`, { notes_admin: adminNotes }, {
            onSuccess: () => { showToast('Refund diproses!', 'success'); },
            onError: (e: any) => { showToast(Object.values(e)[0] as string ?? 'Gagal.', 'error'); },
            onFinish: () => { saving = false; },
        });
    }

    function doProcessReplacement() {
        saving = true;
        router.post(`/admin/returns/${ret.id}/process-replacement`, {}, {
            onSuccess: () => { showToast('Transaksi penggantian barang berhasil dibuat!', 'success'); },
            onError: (e: any) => { showToast(Object.values(e)[0] as string ?? 'Gagal.', 'error'); },
            onFinish: () => { saving = false; },
        });
    }

    function doCompleteRefund() {
        saving = true;
        router.post(`/admin/returns/${ret.id}/complete-refund`, {}, {
            onSuccess: () => { showToast('Retur selesai!', 'success'); },
            onError: (e: any) => { showToast(Object.values(e)[0] as string ?? 'Gagal.', 'error'); },
            onFinish: () => { saving = false; },
        });
    }

    function doSaveCustomerTracking() {
        if (!trackingNumber.trim()) { showToast('Nomor resi wajib diisi.', 'error'); return; }
        saving = true;
        router.post(`/admin/returns/${ret.id}/customer-tracking`, {
            return_tracking_number: trackingNumber,
            return_courier_name: courierName,
        }, {
            onSuccess: () => { showCustomerTrackingModal = false; showToast('Resi customer disimpan!', 'success'); },
            onError: (e: any) => { showToast(Object.values(e)[0] as string ?? 'Gagal.', 'error'); },
            onFinish: () => { saving = false; },
        });
    }

    function doSaveReplacementTracking() {
        if (!replacementTracking.trim()) { showToast('Nomor resi wajib diisi.', 'error'); return; }
        saving = true;
        router.post(`/admin/returns/${ret.id}/replacement-tracking`, {
            replacement_tracking_number: replacementTracking,
            replacement_courier_name: replacementCourier,
        }, {
            onSuccess: () => { showReplacementTrackingModal = false; showToast('Resi penggantian disimpan!', 'success'); },
            onError: (e: any) => { showToast(Object.values(e)[0] as string ?? 'Gagal.', 'error'); },
            onFinish: () => { saving = false; },
        });
    }

    function openMedia(media: any) {
        viewingMedia = media;
        showMediaViewer = true;
    }
</script>

<AdminLayout>
    <div class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto">
        <!-- Back + Header -->
        <div class="flex items-start gap-4 mb-6">
            <a href="/admin/returns" class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 transition shrink-0">
                <i class="ti ti-arrow-left text-lg"></i>
            </a>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    <h3 class="font-outfit font-black text-2xl text-slate-800">Retur #{ret.return_number}</h3>
                    <span
                        class="text-[10px] font-black px-3 py-1.5 rounded-full uppercase tracking-wider"
                        style="background:{statusColor.bg}; color:{statusColor.text}"
                    >
                        {statusLabels[ret.status] ?? ret.status}
                    </span>
                    {#if ret.type === 'refund'}
                        <span class="text-[10px] font-black px-2.5 py-1 rounded-full bg-cyan-50 text-cyan-700 border border-cyan-200">
                            💰 Pengembalian Dana
                        </span>
                    {:else}
                        <span class="text-[10px] font-black px-2.5 py-1 rounded-full bg-purple-50 text-purple-700 border border-purple-200">
                            📦 Penggantian Barang
                        </span>
                    {/if}
                </div>
                <p class="text-xs text-slate-400 font-bold mt-1">Diajukan: {fmtDate(ret.created_at)}</p>
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
                            {(ret.user?.name ?? 'C').substring(0, 2).toUpperCase()}
                        </div>
                        <div>
                            <p class="font-bold text-slate-800">{ret.user?.name ?? '-'}</p>
                            <p class="text-xs text-slate-400">{ret.user?.email ?? ''}</p>
                        </div>
                    </div>

                    <!-- Linked transaction -->
                    <div class="p-3 bg-slate-50 rounded-xl">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Transaksi Asal</p>
                        <a
                            href="/admin/transactions/{ret.transaction_id}"
                            class="font-mono font-bold text-sm hover:underline"
                            style="color:{primary}"
                        >
                            {ret.transaction?.transaction_number}
                        </a>
                        <p class="text-xs text-slate-500 mt-0.5">Grand Total: <strong>{fmt(ret.transaction?.grand_total)}</strong></p>
                    </div>

                    {#if ret.replacement_transaction}
                        <div class="p-3 bg-purple-50 rounded-xl mt-3">
                            <p class="text-[10px] font-bold text-purple-400 uppercase tracking-wider mb-1">Transaksi Penggantian</p>
                            <a
                                href="/admin/transactions/{ret.replacement_transaction_id}"
                                class="font-mono font-bold text-sm text-purple-700 hover:underline"
                            >
                                {ret.replacement_transaction?.transaction_number}
                            </a>
                        </div>
                    {/if}
                </div>

                <!-- Return Reason -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                    <h4 class="font-bold text-slate-800 text-sm mb-3 flex items-center gap-2">
                        <i class="ti ti-message-dots text-base text-slate-400"></i> Alasan Retur
                    </h4>
                    <p class="text-sm text-slate-700 bg-slate-50 rounded-xl p-4 leading-relaxed">{ret.reason}</p>

                    {#if ret.notes_admin}
                        <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-xl">
                            <p class="text-[10px] font-bold text-amber-600 uppercase tracking-wider mb-1">Catatan Admin</p>
                            <p class="text-sm text-amber-800">{ret.notes_admin}</p>
                        </div>
                    {/if}
                </div>

                <!-- Returned Items -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                    <h4 class="font-bold text-slate-800 text-sm mb-4 flex items-center gap-2">
                        <i class="ti ti-package-import text-base text-slate-400"></i> Produk Diretur
                    </h4>
                    <div class="space-y-3">
                        {#each ret.items ?? [] as item (item.id)}
                            <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl">
                                <div class="w-10 h-10 rounded-lg bg-slate-200 flex items-center justify-center shrink-0">
                                    <i class="ti ti-package text-slate-400"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-slate-800 leading-tight">{item.product_name}</p>
                                    {#if item.variant_name}
                                        <span class="text-[10px] font-bold text-slate-500 bg-white border border-slate-200 px-1.5 py-0.5 rounded mt-0.5 inline-block">{item.variant_name}</span>
                                    {/if}
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-xs font-bold text-slate-500">x{item.quantity_returned}</p>
                                    <p class="text-sm font-black text-slate-800">{fmt(item.refund_subtotal)}</p>
                                </div>
                            </div>
                        {/each}
                    </div>
                    <div class="flex justify-between items-center pt-3 border-t border-slate-100 mt-3">
                        <span class="text-sm font-bold text-slate-500">Total Nominal</span>
                        <span class="text-lg font-black" style="color:{primary}">{fmt(ret.refund_amount)}</span>
                    </div>
                </div>

                <!-- Evidence Media -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                    <h4 class="font-bold text-slate-800 text-sm mb-4 flex items-center gap-2">
                        <i class="ti ti-photo text-base text-slate-400"></i> Bukti Retur (Foto/Video)
                    </h4>
                    {#if (ret.media ?? []).length === 0}
                        <p class="text-xs text-slate-400 italic">Tidak ada bukti media.</p>
                    {:else}
                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-3">
                            {#each ret.media ?? [] as media (media.id)}
                                <button
                                    onclick={() => openMedia(media)}
                                    class="relative aspect-square bg-slate-100 rounded-xl overflow-hidden hover:opacity-90 transition group"
                                >
                                    {#if media.file_type === 'video'}
                                        <div class="w-full h-full flex items-center justify-center bg-slate-800">
                                            <i class="ti ti-player-play text-white text-2xl"></i>
                                        </div>
                                        <span class="absolute bottom-1 right-1 text-[9px] bg-black/60 text-white px-1.5 py-0.5 rounded font-bold">VIDEO</span>
                                    {:else}
                                        <img
                                            src={formatMediaUrl(media.file_path)}
                                            alt="Bukti retur"
                                            class="w-full h-full object-cover group-hover:scale-105 transition duration-200"
                                        />
                                    {/if}
                                </button>
                            {/each}
                        </div>
                    {/if}
                </div>
            </div>

            <!-- RIGHT: Actions + Tracking -->
            <div class="space-y-5">

                <!-- Refund Amount & Bank Account -->
                {#if ret.type === 'refund'}
                    <div class="bg-gradient-to-br from-cyan-50 to-blue-50 rounded-2xl border border-cyan-200 p-5">
                        <h4 class="font-bold text-cyan-800 text-sm mb-3 flex items-center gap-2">
                            <i class="ti ti-building-bank text-base"></i> Rekening Refund Customer
                        </h4>
                        {#if primaryBankAccount}
                            <div class="bg-white rounded-xl p-4 border border-cyan-200">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Bank</p>
                                <p class="font-black text-slate-800 text-base mt-0.5">{primaryBankAccount.bank_name}</p>
                                <p class="font-mono font-bold text-lg text-slate-900 mt-1 tracking-wider">{primaryBankAccount.account_number}</p>
                                <p class="text-xs text-slate-500 mt-0.5">a.n. {primaryBankAccount.account_name}</p>
                            </div>
                            <div class="mt-3 p-3 bg-white rounded-xl border border-cyan-200">
                                <p class="text-xs text-slate-500 font-bold">Jumlah Refund</p>
                                <p class="text-xl font-black" style="color:{primary}">{fmt(ret.refund_amount)}</p>
                            </div>
                        {:else}
                            <div class="bg-white/70 rounded-xl p-4 border border-dashed border-cyan-300 text-center">
                                <i class="ti ti-alert-circle text-amber-500 text-xl mb-1 block"></i>
                                <p class="text-xs text-slate-500 font-bold">Customer belum mengisi rekening bank.</p>
                                <p class="text-[10px] text-slate-400 mt-0.5">Hubungi customer untuk info rekening.</p>
                            </div>
                            <div class="mt-3 p-3 bg-white rounded-xl border border-cyan-200">
                                <p class="text-xs text-slate-500 font-bold">Jumlah Refund</p>
                                <p class="text-xl font-black" style="color:{primary}">{fmt(ret.refund_amount)}</p>
                            </div>
                        {/if}
                    </div>
                {/if}

                <!-- Tracking Numbers -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                    <h4 class="font-bold text-slate-800 text-sm mb-4 flex items-center gap-2">
                        <i class="ti ti-truck-delivery text-base text-slate-400"></i> Nomor Resi
                    </h4>

                    <!-- Customer return tracking -->
                    <div class="mb-4">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Resi Barang Retur (dari Customer)</p>
                        {#if ret.return_tracking_number}
                            <div class="p-3 bg-slate-50 rounded-xl">
                                <p class="font-mono font-bold text-sm text-slate-800">{ret.return_tracking_number}</p>
                                {#if ret.return_courier_name}
                                    <p class="text-[10px] text-slate-400 mt-0.5">{ret.return_courier_name}</p>
                                {/if}
                            </div>
                        {:else}
                            <p class="text-xs text-slate-400 italic">Belum ada resi.</p>
                        {/if}
                        {#if ['disetujui', 'barang_dikirim_customer'].includes(ret.status)}
                            <button
                                onclick={() => { trackingNumber = ret.return_tracking_number ?? ''; courierName = ret.return_courier_name ?? ''; showCustomerTrackingModal = true; }}
                                class="mt-2 text-xs font-bold underline"
                                style="color:{primary}"
                            >
                                {ret.return_tracking_number ? 'Ubah Resi' : 'Input Resi'}
                            </button>
                        {/if}
                    </div>

                    <!-- Replacement tracking -->
                    {#if ret.type === 'penggantian_barang'}
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Resi Barang Pengganti (ke Customer)</p>
                            {#if ret.replacement_tracking_number}
                                <div class="p-3 bg-purple-50 rounded-xl border border-purple-200">
                                    <p class="font-mono font-bold text-sm text-purple-800">{ret.replacement_tracking_number}</p>
                                    {#if ret.replacement_courier_name}
                                        <p class="text-[10px] text-purple-400 mt-0.5">{ret.replacement_courier_name}</p>
                                    {/if}
                                </div>
                            {:else}
                                <p class="text-xs text-slate-400 italic">Belum ada resi.</p>
                            {/if}
                            {#if ['selesai'].includes(ret.status) || ret.replacement_transaction_id}
                                <button
                                    onclick={() => { replacementTracking = ret.replacement_tracking_number ?? ''; replacementCourier = ret.replacement_courier_name ?? ''; showReplacementTrackingModal = true; }}
                                    class="mt-2 text-xs font-bold underline"
                                    style="color:{primary}"
                                >
                                    {ret.replacement_tracking_number ? 'Ubah Resi Pengganti' : 'Input Resi Pengganti'}
                                </button>
                            {/if}
                        </div>
                    {/if}
                </div>

                <!-- Action Buttons -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-3">
                    <h4 class="font-bold text-slate-800 text-sm mb-2 flex items-center gap-2">
                        <i class="ti ti-settings text-base text-slate-400"></i> Aksi Admin
                    </h4>

                    {#if ret.status === 'menunggu_review'}
                        <button
                            onclick={() => (showApproveModal = true)}
                            class="w-full py-3 rounded-xl text-sm font-bold text-white transition active:scale-95 flex items-center justify-center gap-2"
                            style="background:#22c55e"
                        >
                            <i class="ti ti-circle-check text-base"></i> Setujui Retur
                        </button>
                        <button
                            onclick={() => (showRejectModal = true)}
                            class="w-full py-3 rounded-xl text-sm font-bold text-white transition active:scale-95 flex items-center justify-center gap-2 bg-red-500 hover:bg-red-600"
                        >
                            <i class="ti ti-circle-x text-base"></i> Tolak Retur
                        </button>
                    {/if}

                    {#if ret.status === 'barang_dikirim_customer'}
                        <button
                            onclick={() => (showReceiptModal = true)}
                            disabled={saving}
                            class="w-full py-3 rounded-xl text-sm font-bold text-white transition active:scale-95 flex items-center justify-center gap-2 disabled:opacity-50"
                            style="background:{primary}"
                        >
                            <i class="ti ti-package-import text-base"></i>
                            Konfirmasi Barang Diterima
                        </button>
                    {/if}

                    {#if ret.status === 'barang_diterima_toko'}
                        {#if ret.type === 'refund'}
                            <div class="space-y-2">
                                <label class="block text-xs font-bold text-slate-600 mb-1">Catatan Refund (opsional)</label>
                                <textarea
                                    bind:value={adminNotes}
                                    rows="2"
                                    placeholder="Catatan untuk customer..."
                                    class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none resize-none"
                                ></textarea>
                                <button
                                    onclick={doProcessRefund}
                                    disabled={saving}
                                    class="w-full py-3 rounded-xl text-sm font-bold text-white transition active:scale-95 flex items-center justify-center gap-2 disabled:opacity-50 bg-cyan-600 hover:bg-cyan-700"
                                >
                                    <i class="ti ti-cash-banknote text-base"></i>
                                    {saving ? 'Memproses...' : 'Proses Pengembalian Dana'}
                                </button>
                            </div>
                        {:else}
                            <button
                                onclick={doProcessReplacement}
                                disabled={saving}
                                class="w-full py-3 rounded-xl text-sm font-bold text-white transition active:scale-95 flex items-center justify-center gap-2 disabled:opacity-50"
                                style="background:{primary}"
                            >
                                <i class="ti ti-package-export text-base"></i>
                                {saving ? 'Memproses...' : 'Buat Transaksi Penggantian'}
                            </button>
                        {/if}
                    {/if}

                    {#if ret.status === 'refund_diproses'}
                        <button
                            onclick={doCompleteRefund}
                            disabled={saving}
                            class="w-full py-3 rounded-xl text-sm font-bold text-white transition active:scale-95 flex items-center justify-center gap-2 disabled:opacity-50 bg-emerald-500 hover:bg-emerald-600"
                        >
                            <i class="ti ti-circle-check text-base"></i>
                            {saving ? 'Memproses...' : 'Tandai Refund Selesai'}
                        </button>
                    {/if}

                    {#if ret.status === 'selesai' || ret.status === 'ditolak'}
                        <div class="text-center py-4">
                            <i class="ti ti-circle-check text-3xl {ret.status === 'selesai' ? 'text-emerald-400' : 'text-red-400'}"></i>
                            <p class="text-sm font-bold text-slate-600 mt-2">
                                {ret.status === 'selesai' ? 'Retur telah selesai.' : 'Retur telah ditolak.'}
                            </p>
                        </div>
                    {/if}
                </div>

                <!-- Timeline -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                    <h4 class="font-bold text-slate-800 text-sm mb-4 flex items-center gap-2">
                        <i class="ti ti-timeline text-base text-slate-400"></i> Timeline
                    </h4>
                    <div class="space-y-3 text-xs">
                        <div class="flex gap-3">
                            <div class="w-2 h-2 rounded-full bg-slate-400 shrink-0 mt-1"></div>
                            <div>
                                <p class="font-bold text-slate-700">Pengajuan Dibuat</p>
                                <p class="text-slate-400">{fmtDate(ret.created_at)}</p>
                            </div>
                        </div>
                        {#if ret.approved_at}
                            <div class="flex gap-3">
                                <div class="w-2 h-2 rounded-full bg-blue-400 shrink-0 mt-1"></div>
                                <div>
                                    <p class="font-bold text-blue-700">Disetujui oleh {ret.approved_by_user?.name ?? 'Admin'}</p>
                                    <p class="text-slate-400">{fmtDate(ret.approved_at)}</p>
                                </div>
                            </div>
                        {/if}
                        {#if ret.rejected_at}
                            <div class="flex gap-3">
                                <div class="w-2 h-2 rounded-full bg-red-400 shrink-0 mt-1"></div>
                                <div>
                                    <p class="font-bold text-red-700">Ditolak</p>
                                    <p class="text-slate-400">{fmtDate(ret.rejected_at)}</p>
                                </div>
                            </div>
                        {/if}
                        {#if ret.received_at}
                            <div class="flex gap-3">
                                <div class="w-2 h-2 rounded-full bg-purple-400 shrink-0 mt-1"></div>
                                <div>
                                    <p class="font-bold text-purple-700">Barang Diterima Toko</p>
                                    <p class="text-slate-400">{fmtDate(ret.received_at)}</p>
                                </div>
                            </div>
                        {/if}
                        {#if ret.refunded_at}
                            <div class="flex gap-3">
                                <div class="w-2 h-2 rounded-full bg-cyan-400 shrink-0 mt-1"></div>
                                <div>
                                    <p class="font-bold text-cyan-700">Refund Diproses</p>
                                    <p class="text-slate-400">{fmtDate(ret.refunded_at)}</p>
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
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" onclick={() => (showApproveModal = false)}>
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative z-10 bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" onclick={(e: any) => e.stopPropagation()}>
            <h3 class="font-bold text-slate-800 text-lg mb-4">Setujui Pengajuan Retur</h3>
            <label class="block text-xs font-bold text-slate-600 mb-2">Catatan untuk Customer (opsional)</label>
            <textarea
                bind:value={adminNotes}
                rows="3"
                placeholder="Instruksi pengiriman barang retur, dll..."
                class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none resize-none mb-5"
            ></textarea>
            <div class="flex gap-3">
                <button onclick={() => (showApproveModal = false)} class="flex-1 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm hover:bg-slate-50">Batal</button>
                <button onclick={doApprove} disabled={saving} class="flex-1 py-3 rounded-xl text-white font-bold text-sm bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50">
                    {saving ? 'Menyimpan...' : 'Ya, Setujui'}
                </button>
            </div>
        </div>
    </div>
{/if}

<!-- Receipt Confirmation Modal -->
{#if showReceiptModal}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" onclick={() => (showReceiptModal = false)}>
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative z-10 bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" onclick={(e: any) => e.stopPropagation()}>
            <h3 class="font-bold text-slate-800 text-lg mb-2">Konfirmasi Penerimaan Barang</h3>
            <p class="text-xs text-slate-400 font-bold mb-5 uppercase tracking-wider">Pilih Opsi Tindakan Stok Produk</p>
            
            <div class="space-y-3 mb-6">
                <!-- Option A: Return to Active Stock -->
                <button
                    type="button"
                    onclick={() => (stockAction = 'active')}
                    class="w-full text-left p-4 rounded-xl border-2 transition flex items-start gap-3 cursor-pointer {stockAction === 'active' ? 'bg-emerald-50/20' : 'bg-white hover:bg-slate-50 border-slate-100'}"
                    style="border-color: {stockAction === 'active' ? '#10b981' : '#e2e8f0'}"
                >
                    <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 mt-0.5" 
                         style="border-color: {stockAction === 'active' ? '#10b981' : '#cbd5e1'}; color: {stockAction === 'active' ? '#10b981' : 'transparent'}">
                        <div class="w-2.5 h-2.5 rounded-full bg-current"></div>
                    </div>
                    <div>
                        <span class="text-xs font-black text-slate-800 block">Kembalikan ke Stok Aktif</span>
                        <span class="text-[10px] text-slate-400 font-bold leading-normal block mt-1">Barang layak jual kembali. Stok produk aktif di sistem akan bertambah otomatis.</span>
                    </div>
                </button>

                <!-- Option B: Damaged Stock -->
                <button
                    type="button"
                    onclick={() => (stockAction = 'damaged')}
                    class="w-full text-left p-4 rounded-xl border-2 transition flex items-start gap-3 cursor-pointer {stockAction === 'damaged' ? 'bg-rose-50/20' : 'bg-white hover:bg-slate-50 border-slate-100'}"
                    style="border-color: {stockAction === 'damaged' ? '#ef4444' : '#e2e8f0'}"
                >
                    <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 mt-0.5" 
                         style="border-color: {stockAction === 'damaged' ? '#ef4444' : '#cbd5e1'}; color: {stockAction === 'damaged' ? '#ef4444' : 'transparent'}">
                        <div class="w-2.5 h-2.5 rounded-full bg-current"></div>
                    </div>
                    <div>
                        <span class="text-xs font-black text-slate-800 block">Catat sebagai Stok Rusak</span>
                        <span class="text-[10px] text-slate-400 font-bold leading-normal block mt-1">Barang cacat/rusak dan tidak layak jual. Stok produk aktif di sistem TIDAK akan bertambah.</span>
                    </div>
                </button>
            </div>

            <div class="flex gap-3">
                <button onclick={() => (showReceiptModal = false)} class="flex-1 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm hover:bg-slate-50">Batal</button>
                <button onclick={doConfirmReceipt} disabled={saving} class="flex-1 py-3 rounded-xl text-white font-bold text-sm bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 flex items-center justify-center gap-1.5">
                    {#if saving}
                        <i class="ti ti-loader animate-spin text-sm"></i>
                    {/if}
                    Konfirmasi Penerimaan
                </button>
            </div>
        </div>
    </div>
{/if}

<!-- Reject Modal -->
{#if showRejectModal}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" onclick={() => (showRejectModal = false)}>
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative z-10 bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" onclick={(e: any) => e.stopPropagation()}>
            <h3 class="font-bold text-slate-800 text-lg mb-4">Tolak Pengajuan Retur</h3>
            <label class="block text-xs font-bold text-slate-600 mb-2">Alasan Penolakan <span class="text-red-500">*</span></label>
            <textarea
                bind:value={rejectReason}
                rows="3"
                placeholder="Jelaskan alasan penolakan retur..."
                class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none resize-none mb-5"
            ></textarea>
            <div class="flex gap-3">
                <button onclick={() => (showRejectModal = false)} class="flex-1 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm hover:bg-slate-50">Batal</button>
                <button onclick={doReject} disabled={saving} class="flex-1 py-3 rounded-xl text-white font-bold text-sm bg-red-500 hover:bg-red-600 disabled:opacity-50">
                    {saving ? 'Menyimpan...' : 'Ya, Tolak'}
                </button>
            </div>
        </div>
    </div>
{/if}

<!-- Customer Tracking Modal -->
{#if showCustomerTrackingModal}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" onclick={() => (showCustomerTrackingModal = false)}>
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative z-10 bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" onclick={(e: any) => e.stopPropagation()}>
            <h3 class="font-bold text-slate-800 text-lg mb-4">Resi Pengiriman Barang Retur (Customer)</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-2">Nomor Resi <span class="text-red-500">*</span></label>
                    <input type="text" bind:value={trackingNumber} placeholder="Contoh: JNE1234567890" class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none font-mono" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-2">Nama Kurir</label>
                    <input type="text" bind:value={courierName} placeholder="JNE, J&T, SiCepat, dll." class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none" />
                </div>
            </div>
            <div class="flex gap-3 mt-5">
                <button onclick={() => (showCustomerTrackingModal = false)} class="flex-1 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm">Batal</button>
                <button onclick={doSaveCustomerTracking} disabled={saving} class="flex-1 py-3 rounded-xl text-white font-bold text-sm disabled:opacity-50" style="background:{primary}">
                    {saving ? 'Menyimpan...' : 'Simpan Resi'}
                </button>
            </div>
        </div>
    </div>
{/if}

<!-- Replacement Tracking Modal -->
{#if showReplacementTrackingModal}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" onclick={() => (showReplacementTrackingModal = false)}>
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative z-10 bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" onclick={(e: any) => e.stopPropagation()}>
            <h3 class="font-bold text-slate-800 text-lg mb-4">Resi Barang Pengganti (ke Customer)</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-2">Nomor Resi Pengganti <span class="text-red-500">*</span></label>
                    <input type="text" bind:value={replacementTracking} placeholder="Contoh: JNE1234567890" class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none font-mono" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-2">Nama Kurir</label>
                    <input type="text" bind:value={replacementCourier} placeholder="JNE, J&T, SiCepat, dll." class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none" />
                </div>
            </div>
            <div class="flex gap-3 mt-5">
                <button onclick={() => (showReplacementTrackingModal = false)} class="flex-1 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm">Batal</button>
                <button onclick={doSaveReplacementTracking} disabled={saving} class="flex-1 py-3 rounded-xl text-white font-bold text-sm disabled:opacity-50" style="background:{primary}">
                    {saving ? 'Menyimpan...' : 'Simpan Resi Pengganti'}
                </button>
            </div>
        </div>
    </div>
{/if}

<!-- Media Viewer -->
{#if showMediaViewer && viewingMedia}
    <div class="fixed inset-0 z-[100] flex items-center justify-center" onclick={() => (showMediaViewer = false)}>
        <div class="absolute inset-0 bg-black/90 backdrop-blur-sm"></div>
        <div class="relative z-10 max-w-3xl w-full p-4" onclick={(e: any) => e.stopPropagation()}>
            <button onclick={() => (showMediaViewer = false)} class="absolute top-2 right-2 w-10 h-10 rounded-full bg-white/20 text-white flex items-center justify-center hover:bg-white/30 transition">
                <i class="ti ti-x text-lg"></i>
            </button>
            {#if viewingMedia.file_type === 'video'}
                <!-- svelte-ignore a11y_media_has_caption -->
                <video src={formatMediaUrl(viewingMedia.file_path)} controls class="w-full max-h-[80vh] rounded-xl" autoplay></video>
            {:else}
                <img src={formatMediaUrl(viewingMedia.file_path)} alt="Bukti retur" class="w-full max-h-[85vh] object-contain rounded-xl" />
            {/if}
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
