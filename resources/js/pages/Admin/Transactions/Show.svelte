<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';

    let { transaction, statusLabels = {}, storeName = '', storeLogo = '' } = $props();

    const primary = $derived((page.props as any).theme?.primary_color ?? '#0c4cb4');
    const secondary = $derived((page.props as any).theme?.secondary_color ?? '#fa7315');

    let newStatus = $state(transaction.status);
    let cancelReason = $state('');
    let showStatusModal = $state(false);
    let showRejectModal = $state(false);
    let rejectNotes = $state('');
    let isUpdating = $state(false);

    const paymentMethod = $derived(transaction.payment_method ?? transaction.paymentMethod);
    const customerAddress = $derived(transaction.customer_address ?? transaction.customerAddress);

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
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    const statusSteps = [
        { key: 'belum_bayar', label: 'Belum Bayar', icon: 'ti-cash' },
        { key: 'menunggu', label: 'Menunggu', icon: 'ti-clock' },
        { key: 'diproses', label: 'Diproses', icon: 'ti-settings' },
        { key: 'dikemas', label: 'Dikemas', icon: 'ti-package' },
        { key: 'dikirim', label: 'Dikirim', icon: 'ti-truck' },
        { key: 'selesai', label: 'Selesai', icon: 'ti-circle-check' },
    ];

    const statusIndex = $derived(
        transaction.status === 'batal'
            ? -1
            : statusSteps.findIndex((s) => s.key === transaction.status)
    );

    const statusColors: Record<string, { bg: string; text: string }> = {
        belum_bayar: { bg: '#fef3c7', text: '#92400e' },
        menunggu: { bg: '#dbeafe', text: '#1e40af' },
        diproses: { bg: '#ede9fe', text: '#5b21b6' },
        dikemas: { bg: '#cffafe', text: '#0e7490' },
        dikirim: { bg: '#ffedd5', text: '#9a3412' },
        selesai: { bg: '#dcfce7', text: '#166534' },
        batal: { bg: '#fee2e2', text: '#991b1b' },
    };

    const latestPayment = $derived(
        transaction.payments?.[transaction.payments.length - 1] ?? null
    );

    function updateStatus() {
        isUpdating = true;
        router.post(`/admin/transactions/${transaction.id}/status`, {
            status: newStatus,
            cancel_reason: cancelReason,
        }, {
            onSuccess: () => {
                showToast('Status transaksi berhasil diperbarui.', 'success');
                showStatusModal = false;
            },
            onError: () => {
                showToast('Gagal memperbarui status.', 'error');
            },
            onFinish: () => { isUpdating = false; },
        });
    }

    function confirmPayment() {
        isUpdating = true;
        router.post(`/admin/transactions/${transaction.id}/confirm-payment`, {}, {
            onSuccess: () => {
                showToast('Pembayaran berhasil dikonfirmasi.', 'success');
            },
            onError: () => {
                showToast('Gagal mengkonfirmasi pembayaran.', 'error');
            },
            onFinish: () => { isUpdating = false; },
        });
    }

    function rejectPayment() {
        if (!rejectNotes.trim()) {
            showToast('Masukkan alasan penolakan.', 'error');
            return;
        }
        isUpdating = true;
        router.post(`/admin/transactions/${transaction.id}/reject-payment`, {
            notes: rejectNotes,
        }, {
            onSuccess: () => {
                showToast('Pembayaran ditolak.', 'success');
                showRejectModal = false;
                rejectNotes = '';
            },
            onError: () => {
                showToast('Gagal menolak pembayaran.', 'error');
            },
            onFinish: () => { isUpdating = false; },
        });
    }
</script>

<AdminLayout {storeName} {storeLogo}>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <a
                href="/admin/transactions"
                class="p-2 hover:bg-slate-100 rounded-xl transition"
            >
                <i class="ti ti-arrow-left text-xl text-slate-700"></i>
            </a>
            <div class="flex-1">
                <h1 class="text-xl font-black text-slate-800">{transaction.transaction_number}</h1>
                <p class="text-sm text-slate-500">{fmtDate(transaction.created_at)}</p>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="text-xs font-black px-3 py-1.5 rounded-full"
                    style="background:{(statusColors[transaction.status] ?? { bg: '#f1f5f9', text: '#475569' }).bg}; color:{(statusColors[transaction.status] ?? { bg: '#f1f5f9', text: '#475569' }).text}"
                >
                    {statusLabels[transaction.status] ?? transaction.status}
                </span>
                <button
                    onclick={() => (showStatusModal = true)}
                    class="px-4 py-2 rounded-xl text-sm font-bold text-white transition"
                    style="background:{primary}"
                >
                    <i class="ti ti-edit text-sm mr-1"></i>Ubah Status
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-4">
                <!-- Status Tracker -->
                {#if transaction.status !== 'batal'}
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                        <h3 class="font-bold text-slate-800 text-sm mb-4">Perjalanan Pesanan</h3>
                        <div class="flex items-start justify-between relative">
                            <div class="absolute left-4 right-4 top-4 h-0.5 bg-slate-200 z-0"></div>
                            {#each statusSteps as step, i}
                                {@const isCompleted = statusIndex >= i}
                                {@const isCurrent = statusIndex === i}
                                <div class="flex flex-col items-center gap-1 z-10 flex-1">
                                    <div
                                        class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-all"
                                        style={isCompleted
                                            ? `background:${primary}; border-color:${primary}`
                                            : 'background:white; border-color:#e2e8f0'}
                                    >
                                        <i class="ti {step.icon} text-sm" style={isCompleted ? 'color:white' : 'color:#94a3b8'}></i>
                                    </div>
                                    <span class="text-[9px] font-semibold text-center" style={isCurrent ? `color:${primary}` : isCompleted ? 'color:#64748b' : 'color:#94a3b8'}>
                                        {step.label}
                                    </span>
                                </div>
                            {/each}
                        </div>
                    </div>
                {:else}
                    <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
                        <div class="flex items-center gap-3">
                            <i class="ti ti-x text-2xl text-red-500"></i>
                            <div>
                                <p class="font-bold text-red-700">Pesanan Dibatalkan</p>
                                {#if transaction.cancel_reason}
                                    <p class="text-xs text-red-600">{transaction.cancel_reason}</p>
                                {/if}
                            </div>
                        </div>
                    </div>
                {/if}

                <!-- Payment Proof Review -->
                {#if latestPayment?.proof_image}
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                        <h3 class="font-bold text-slate-800 text-sm mb-3">Bukti Pembayaran</h3>
                        <div class="flex gap-4">
                            <a href="/storage/{latestPayment.proof_image}" target="_blank" class="shrink-0">
                                <img
                                    src="/storage/{latestPayment.proof_image}"
                                    alt="Bukti Bayar"
                                    class="w-32 h-32 object-cover rounded-xl border border-slate-200 hover:opacity-90 transition"
                                />
                            </a>
                            <div class="flex-1">
                                <p class="text-xs text-slate-500">Diunggah: {fmtDate(latestPayment.proof_uploaded_at)}</p>
                                {#if latestPayment.status === 'confirmed'}
                                    <div class="mt-2 flex items-center gap-2 text-green-600">
                                        <i class="ti ti-circle-check text-lg"></i>
                                        <p class="text-sm font-bold">Dikonfirmasi oleh {latestPayment.confirmedByUser?.name}</p>
                                    </div>
                                    <p class="text-xs text-slate-400">{fmtDate(latestPayment.confirmed_at)}</p>
                                {:else if latestPayment.status === 'rejected'}
                                    <div class="mt-2 flex items-center gap-2 text-red-600">
                                        <i class="ti ti-x text-lg"></i>
                                        <p class="text-sm font-bold">Ditolak</p>
                                    </div>
                                    {#if latestPayment.notes}
                                        <p class="text-xs text-red-500">{latestPayment.notes}</p>
                                    {/if}
                                {:else}
                                    <p class="text-sm font-semibold text-amber-600 mt-1">Menunggu Konfirmasi</p>
                                    <div class="flex gap-2 mt-3">
                                        <button
                                            onclick={confirmPayment}
                                            disabled={isUpdating}
                                            class="flex items-center gap-1 px-4 py-2 rounded-xl text-xs font-bold text-white bg-green-500 hover:bg-green-600 transition disabled:opacity-50"
                                        >
                                            <i class="ti ti-check text-sm"></i>Konfirmasi
                                        </button>
                                        <button
                                            onclick={() => (showRejectModal = true)}
                                            class="flex items-center gap-1 px-4 py-2 rounded-xl text-xs font-bold text-white bg-red-500 hover:bg-red-600 transition"
                                        >
                                            <i class="ti ti-x text-sm"></i>Tolak
                                        </button>
                                    </div>
                                {/if}
                            </div>
                        </div>
                    </div>
                {/if}

                <!-- Items -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="px-4 pt-4 pb-2">
                        <h3 class="font-bold text-slate-800 text-sm">Produk Dipesan</h3>
                    </div>
                    <div class="divide-y divide-slate-50">
                        {#each transaction.items ?? [] as item}
                            <div class="px-4 py-3 flex gap-3">
                                {#if item.product_image}
                                    <img
                                        src="/storage/{item.product_image}"
                                        alt={item.product_name}
                                        class="w-14 h-14 object-cover rounded-lg shrink-0 border border-slate-100"
                                        onerror={(e: any) => { e.target.src = '/images/placeholder.png'; }}
                                    />
                                {:else}
                                    <div class="w-14 h-14 rounded-lg bg-slate-100 shrink-0 flex items-center justify-center">
                                        <i class="ti ti-package text-2xl text-slate-300"></i>
                                    </div>
                                {/if}
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-slate-800">{item.product_name}</p>
                                    {#if item.variant_name}
                                        <p class="text-xs text-slate-500">{item.variant_name}</p>
                                    {/if}
                                    <p class="text-xs text-slate-400 mt-0.5">SKU: {item.product_sku}</p>
                                    <div class="grid grid-cols-4 gap-2 mt-2 text-xs">
                                        <div>
                                            <p class="text-slate-400">HPP</p>
                                            <p class="font-semibold text-slate-700">{fmt(item.hpp)}</p>
                                        </div>
                                        <div>
                                            <p class="text-slate-400">Harga Jual</p>
                                            <p class="font-semibold text-slate-700">{fmt(item.harga_jual)}</p>
                                        </div>
                                        <div>
                                            <p class="text-slate-400">Diskon</p>
                                            <p class="font-semibold text-slate-700">{fmt(item.diskon_item)}</p>
                                        </div>
                                        <div>
                                            <p class="text-slate-400">Qty</p>
                                            <p class="font-semibold text-slate-700">{item.quantity}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-xs text-slate-400">Subtotal</p>
                                    <p class="text-sm font-black text-slate-800">{fmt(item.subtotal)}</p>
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-4">
                <!-- Customer Info -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                    <h3 class="font-bold text-slate-800 text-sm mb-3">Informasi Customer</h3>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm shrink-0" style="background:{primary}">
                            {(transaction.user?.name ?? 'C').substring(0, 2).toUpperCase()}
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800 text-sm">{transaction.user?.name}</p>
                            <p class="text-xs text-slate-500">{transaction.user?.email}</p>
                        </div>
                    </div>
                    {#if customerAddress}
                        <div class="bg-slate-50 rounded-xl p-3 text-xs">
                            <p class="font-semibold text-slate-700">{customerAddress.receiver_name}</p>
                            <p class="text-slate-500">{customerAddress.phone_number}</p>
                            <p class="text-slate-600 mt-1 leading-relaxed">{customerAddress.full_address}</p>
                        </div>
                    {/if}
                </div>

                <!-- Payment Summary -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                    <h3 class="font-bold text-slate-800 text-sm mb-3">Rincian Pembayaran</h3>
                    <div class="space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Subtotal</span>
                            <span class="font-semibold">{fmt(transaction.subtotal)}</span>
                        </div>
                        {#if transaction.discount_amount > 0}
                            <div class="flex justify-between text-green-600">
                                <span>Diskon Voucher</span>
                                <span class="font-semibold">-{fmt(transaction.discount_amount)}</span>
                            </div>
                        {/if}
                        <div class="flex justify-between">
                            <span class="text-slate-500">Ongkir ({transaction.shipping_courier?.toUpperCase()} {transaction.shipping_service})</span>
                            <span class="font-semibold">{fmt(transaction.shipping_fee)}</span>
                        </div>
                        {#if transaction.shipping_discount > 0}
                            <div class="flex justify-between text-green-600">
                                <span>Diskon Ongkir</span>
                                <span>-{fmt(transaction.shipping_discount)}</span>
                            </div>
                        {/if}
                        {#if transaction.admin_fee > 0}
                            <div class="flex justify-between">
                                <span class="text-slate-500">Biaya Admin</span>
                                <span class="font-semibold">{fmt(transaction.admin_fee)}</span>
                            </div>
                        {/if}
                        {#if transaction.application_fee > 0}
                            <div class="flex justify-between">
                                <span class="text-slate-500">Biaya Aplikasi</span>
                                <span class="font-semibold">{fmt(transaction.application_fee)}</span>
                            </div>
                        {/if}
                        <div class="border-t border-slate-100 pt-2 flex justify-between">
                            <span class="font-bold text-slate-800">Total</span>
                            <span class="font-black text-base" style="color:{primary}">{fmt(transaction.grand_total)}</span>
                        </div>
                    </div>
                    {#if paymentMethod}
                        <div class="mt-3 pt-3 border-t border-slate-100 text-xs text-slate-500">
                            <i class="ti ti-credit-card mr-1"></i>
                            {paymentMethod.name}
                            {#if paymentMethod.type === 'manual' && paymentMethod.bank_name}
                                · {paymentMethod.bank_name}
                            {/if}
                        </div>
                    {/if}
                </div>

                <!-- Notes -->
                {#if transaction.notes}
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                        <h3 class="font-bold text-slate-800 text-sm mb-2">Catatan Customer</h3>
                        <p class="text-sm text-slate-600">{transaction.notes}</p>
                    </div>
                {/if}
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    {#if showStatusModal}
        <div class="fixed inset-0 z-50 flex items-center justify-center" onclick={() => (showStatusModal = false)}>
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div class="relative z-10 bg-white rounded-2xl p-6 w-full max-w-sm mx-4" onclick={(e: any) => e.stopPropagation()}>
                <h3 class="font-bold text-slate-800 mb-4">Ubah Status Transaksi</h3>
                <select
                    bind:value={newStatus}
                    class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none mb-3 appearance-none bg-white"
                >
                    {#each Object.entries(statusLabels) as [key, label]}
                        <option value={key}>{label as string}</option>
                    {/each}
                </select>
                {#if newStatus === 'batal'}
                    <textarea
                        bind:value={cancelReason}
                        rows="3"
                        placeholder="Alasan pembatalan (wajib)"
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none mb-3 resize-none"
                    ></textarea>
                {/if}
                <div class="flex gap-3">
                    <button onclick={() => (showStatusModal = false)} class="flex-1 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Batal
                    </button>
                    <button
                        onclick={updateStatus}
                        disabled={isUpdating || (newStatus === 'batal' && !cancelReason.trim())}
                        class="flex-1 py-2.5 rounded-xl text-sm font-bold text-white transition disabled:opacity-50"
                        style="background:{primary}"
                    >
                        {isUpdating ? 'Menyimpan...' : 'Simpan'}
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Reject Payment Modal -->
    {#if showRejectModal}
        <div class="fixed inset-0 z-50 flex items-center justify-center" onclick={() => (showRejectModal = false)}>
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div class="relative z-10 bg-white rounded-2xl p-6 w-full max-w-sm mx-4" onclick={(e: any) => e.stopPropagation()}>
                <h3 class="font-bold text-slate-800 mb-4">Tolak Pembayaran</h3>
                <p class="text-sm text-slate-500 mb-3">Masukkan alasan penolakan. Customer akan diminta upload ulang.</p>
                <textarea
                    bind:value={rejectNotes}
                    rows="3"
                    placeholder="Alasan penolakan..."
                    class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none mb-4 resize-none"
                ></textarea>
                <div class="flex gap-3">
                    <button onclick={() => (showRejectModal = false)} class="flex-1 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Batal
                    </button>
                    <button
                        onclick={rejectPayment}
                        disabled={isUpdating}
                        class="flex-1 py-2.5 rounded-xl text-sm font-bold text-white bg-red-500 hover:bg-red-600 transition disabled:opacity-50"
                    >
                        {isUpdating ? 'Memproses...' : 'Tolak Pembayaran'}
                    </button>
                </div>
            </div>
        </div>
    {/if}
</AdminLayout>
