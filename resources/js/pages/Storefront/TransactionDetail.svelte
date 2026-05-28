<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';

    let { transaction, statusLabels = {}, storeName = '', storeLogo = '' } = $props();

    const primary = $derived((page.props as any).theme?.primary_color ?? '#ee4d2d');
    const secondary = $derived((page.props as any).theme?.secondary_color ?? '#fa7315');

    let proofFile: File | null = $state(null);
    let proofPreview = $state('');
    let uploadingProof = $state(false);
    let showUploadModal = $state(false);

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

    function formatImagePath(path: string | null | undefined): string {
        if (!path) return '/images/placeholder.png';
        if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
            return path;
        }
        return '/storage/' + path;
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

    const statusColors: Record<string, string> = {
        belum_bayar: '#f59e0b',
        menunggu: '#3b82f6',
        diproses: '#8b5cf6',
        dikemas: '#06b6d4',
        dikirim: '#f97316',
        selesai: '#22c55e',
        batal: '#ef4444',
    };

    const currentStatusColor = $derived(statusColors[transaction.status] ?? '#64748b');
    const paymentMethod = $derived(transaction.payment_method ?? transaction.paymentMethod);
    const customerAddress = $derived(transaction.customer_address ?? transaction.customerAddress);
    const latestPayment = $derived(transaction.payments?.[transaction.payments.length - 1] ?? null);

    const canUploadProof = $derived(
        (transaction.status === 'belum_bayar' || transaction.status === 'menunggu') &&
        paymentMethod?.type === 'manual'
    );

    function handleFileChange(e: Event) {
        const input = e.target as HTMLInputElement;
        if (input.files?.[0]) {
            proofFile = input.files[0];
            proofPreview = URL.createObjectURL(proofFile);
        }
    }

    function uploadProof() {
        if (!proofFile) return;
        uploadingProof = true;

        const form = new FormData();
        form.append('proof_image', proofFile);
        form.append('_method', 'POST');

        router.post(`/transactions/${transaction.id}/upload-proof`, form as any, {
            forceFormData: true,
            onSuccess: () => {
                showToast('Bukti pembayaran berhasil diunggah!', 'success');
                showUploadModal = false;
                proofFile = null;
                proofPreview = '';
            },
            onError: () => {
                showToast('Gagal mengunggah bukti pembayaran.', 'error');
            },
            onFinish: () => {
                uploadingProof = false;
            },
        });
    }
</script>

<StorefrontLayout {storeName} {storeLogo} hideMobileFooter={true}>
    <div class="min-h-screen bg-slate-50">
        <!-- Header -->
        <div class="bg-white border-b border-slate-200 sticky top-0 z-30">
            <div class="max-w-3xl mx-auto px-4 h-14 flex items-center gap-3">
                <Link href="/transactions" class="p-2 hover:bg-slate-100 rounded-full transition">
                    <i class="ti ti-arrow-left text-xl text-slate-700"></i>
                </Link>
                <div>
                    <h1 class="text-sm font-bold text-slate-800 leading-tight">Detail Pesanan</h1>
                    <p class="text-xs text-slate-500 leading-tight">{transaction.transaction_number}</p>
                </div>
            </div>
        </div>

        <div class="max-w-3xl mx-auto px-4 py-4 space-y-4 pb-12">
            <!-- Status Banner -->
            {#if transaction.status === 'batal'}
                <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                            <i class="ti ti-x text-2xl text-red-500"></i>
                        </div>
                        <div>
                            <p class="font-bold text-red-700">Pesanan Dibatalkan</p>
                            {#if transaction.cancel_reason}
                                <p class="text-xs text-red-600 mt-0.5">{transaction.cancel_reason}</p>
                            {/if}
                            {#if transaction.cancelled_at}
                                <p class="text-xs text-red-400 mt-0.5">{fmtDate(transaction.cancelled_at)}</p>
                            {/if}
                        </div>
                    </div>
                </div>
            {:else}
                <!-- Status Steps -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                    <div class="flex items-center mb-4">
                        <div
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-white text-xs font-bold"
                            style="background-color:{currentStatusColor}"
                        >
                            <i class="ti ti-circle-check text-sm"></i>
                            {statusLabels[transaction.status] ?? transaction.status}
                        </div>
                        <span class="ml-auto text-xs text-slate-400">{fmtDate(transaction.created_at)}</span>
                    </div>

                    <!-- Progress Steps -->
                    <div class="flex items-center justify-between relative">
                        <!-- Progress line -->
                        <div class="absolute left-4 right-4 top-4 h-0.5 bg-slate-200 z-0"></div>
                        {#if statusIndex >= 0}
                            <div
                                class="absolute left-4 top-4 h-0.5 z-0 transition-all duration-500"
                                style="background:{primary}; width: calc({statusIndex > 0 ? (statusIndex / (statusSteps.length - 1)) * 100 : 0}% - 2rem); right: auto;"
                            ></div>
                        {/if}

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
                                    <i
                                        class="ti {step.icon} text-sm"
                                        style={isCompleted ? 'color:white' : 'color:#94a3b8'}
                                    ></i>
                                </div>
                                <span
                                    class="text-[9px] font-semibold text-center leading-tight"
                                    style={isCurrent ? `color:${primary}` : isCompleted ? 'color:#64748b' : 'color:#94a3b8'}
                                >
                                    {step.label}
                                </span>
                            </div>
                        {/each}
                    </div>
                </div>
            {/if}

            <!-- Upload Proof (if manual payment & belum bayar/menunggu) -->
            {#if canUploadProof}
                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4">
                    <div class="flex items-start gap-3">
                        <i class="ti ti-alert-triangle text-2xl text-amber-500 shrink-0 mt-0.5"></i>
                        <div class="flex-1">
                            <p class="font-bold text-amber-800 text-sm">Segera Lakukan Pembayaran</p>
                            <div class="mt-2 bg-white rounded-xl p-3 border border-amber-200">
                                <p class="text-xs font-bold text-slate-700 mb-1">Transfer ke:</p>
                                <p class="text-sm font-bold text-slate-800">{paymentMethod?.bank_name}</p>
                                <p class="text-lg font-black text-slate-900">{paymentMethod?.account_number}</p>
                                <p class="text-xs text-slate-500">a.n. {paymentMethod?.account_name}</p>
                                <div class="mt-2 pt-2 border-t border-slate-100">
                                    <p class="text-xs text-slate-600">Jumlah transfer:</p>
                                    <p class="text-base font-black" style="color:{primary}">{fmt(transaction.grand_total)}</p>
                                </div>
                            </div>
                            {#if latestPayment?.proof_image}
                                <div class="mt-2">
                                    <p class="text-xs text-slate-600 mb-1">Bukti bayar yang diunggah:</p>
                                    <img
                                        src={formatImagePath(latestPayment.proof_image)}
                                        alt="Bukti Pembayaran"
                                        class="w-32 h-32 object-cover rounded-xl border border-slate-200"
                                    />
                                    <p class="text-xs text-slate-400 mt-1">
                                        Diunggah {fmtDate(latestPayment.proof_uploaded_at)}
                                        {#if latestPayment.status === 'rejected'}
                                            <span class="text-red-500 font-semibold"> · Ditolak: {latestPayment.notes}</span>
                                        {/if}
                                    </p>
                                </div>
                            {/if}
                            <button
                                onclick={() => (showUploadModal = true)}
                                class="mt-3 flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold text-white transition"
                                style="background:{primary}"
                            >
                                <i class="ti ti-upload text-sm"></i>
                                {latestPayment?.proof_image ? 'Ganti Bukti Bayar' : 'Upload Bukti Bayar'}
                            </button>
                        </div>
                    </div>
                </div>
            {/if}

            <!-- Shipping Address -->
            {#if customerAddress}
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="ti ti-map-pin text-base" style="color:{primary}"></i>
                        <span class="font-bold text-slate-800 text-sm">Alamat Pengiriman</span>
                    </div>
                    <p class="text-sm font-semibold text-slate-800">{customerAddress.receiver_name}</p>
                    <p class="text-xs text-slate-500">{customerAddress.phone_number}</p>
                    <p class="text-xs text-slate-600 mt-1 leading-relaxed">{customerAddress.full_address}</p>
                    {#if customerAddress.regency_name}
                        <p class="text-xs text-slate-500">{customerAddress.district_name}, {customerAddress.regency_name}, {customerAddress.province_name} {customerAddress.postal_code}</p>
                    {/if}
                    {#if transaction.shipping_courier}
                        <div class="mt-2 pt-2 border-t border-slate-100 flex items-center gap-2 text-xs text-slate-600">
                            <i class="ti ti-truck"></i>
                            <span class="font-semibold uppercase">{transaction.shipping_courier}</span>
                            <span>{transaction.shipping_service}</span>
                            {#if transaction.shipping_etd}
                                <span>· Est. {transaction.shipping_etd} hari</span>
                            {/if}
                        </div>
                    {/if}
                </div>
            {/if}

            <!-- Order Items -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-4 pt-4 pb-2">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-package text-base" style="color:{primary}"></i>
                        <span class="font-bold text-slate-800 text-sm">Produk Dipesan</span>
                    </div>
                </div>
                <div class="divide-y divide-slate-100">
                    {#each transaction.items ?? [] as item}
                        <div class="px-4 py-3 flex gap-3">
                            {#if item.product_image}
                                <img
                                    src={formatImagePath(item.product_image)}
                                    alt={item.product_name}
                                    class="w-14 h-14 object-cover rounded-lg shrink-0 border border-slate-100"
                                    onerror={(e: any) => { e.target.src = '/images/placeholder.png'; }}
                                />
                            {:else}
                                <div class="w-14 h-14 rounded-lg bg-slate-100 shrink-0 flex items-center justify-center">
                                    <i class="ti ti-package text-2xl text-slate-300"></i>
                                </div>
                            {/if}
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-800 leading-tight">{item.product_name}</p>
                                {#if item.variant_name}
                                    <p class="text-xs text-slate-500">{item.variant_name}</p>
                                {/if}
                                {#if item.is_gift_item}
                                    <span class="inline-block text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-green-100 text-green-700 mt-0.5">Gratis</span>
                                {/if}
                                <div class="flex items-center justify-between mt-1.5">
                                    <span class="text-xs text-slate-500">x{item.quantity}</span>
                                    <div class="text-right">
                                        {#if item.diskon_item > 0}
                                            <p class="text-xs text-slate-400 line-through">{fmt(item.harga_jual)}</p>
                                        {/if}
                                        <p class="text-sm font-bold" style="color:{primary}">{fmt(item.harga_akhir)}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    {/each}
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                <div class="flex items-center gap-2 mb-3">
                    <i class="ti ti-receipt text-base" style="color:{primary}"></i>
                    <span class="font-bold text-slate-800 text-sm">Rincian Pembayaran</span>
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-600">Subtotal Produk</span>
                        <span class="font-semibold">{fmt(transaction.subtotal)}</span>
                    </div>
                    {#if transaction.discount_amount > 0}
                        <div class="flex justify-between text-green-600">
                            <span>Diskon Voucher{transaction.voucher_code ? ` (${transaction.voucher_code})` : ''}</span>
                            <span class="font-semibold">-{fmt(transaction.discount_amount)}</span>
                        </div>
                    {/if}
                    <div class="flex justify-between">
                        <span class="text-slate-600">Ongkos Kirim</span>
                        <span class="font-semibold">{fmt(transaction.shipping_fee)}</span>
                    </div>
                    {#if transaction.shipping_discount > 0}
                        <div class="flex justify-between text-green-600">
                            <span>Gratis Ongkir</span>
                            <span class="font-semibold">-{fmt(transaction.shipping_discount)}</span>
                        </div>
                    {/if}
                    {#if transaction.admin_fee > 0}
                        <div class="flex justify-between">
                            <span class="text-slate-600">Biaya Admin</span>
                            <span class="font-semibold">{fmt(transaction.admin_fee)}</span>
                        </div>
                    {/if}
                    {#if transaction.application_fee > 0}
                        <div class="flex justify-between">
                            <span class="text-slate-600">Biaya Aplikasi</span>
                            <span class="font-semibold">{fmt(transaction.application_fee)}</span>
                        </div>
                    {/if}
                    <div class="border-t border-slate-100 pt-2 flex justify-between">
                        <span class="font-bold text-slate-800">Total</span>
                        <span class="font-black text-lg" style="color:{primary}">{fmt(transaction.grand_total)}</span>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="mt-3 pt-3 border-t border-slate-100 flex items-center gap-2">
                    <i class="ti ti-credit-card text-slate-400"></i>
                    <span class="text-xs text-slate-600">
                        {paymentMethod?.name ?? 'Metode Pembayaran'}
                        {#if latestPayment?.status === 'confirmed'}
                            <span class="ml-2 text-green-600 font-semibold">✓ Dikonfirmasi</span>
                        {:else if latestPayment?.status === 'rejected'}
                            <span class="ml-2 text-red-500 font-semibold">✗ Ditolak</span>
                        {/if}
                    </span>
                </div>
            </div>

            <!-- Notes -->
            {#if transaction.notes}
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="ti ti-notes text-base" style="color:{primary}"></i>
                        <span class="font-bold text-slate-800 text-sm">Catatan</span>
                    </div>
                    <p class="text-sm text-slate-600">{transaction.notes}</p>
                </div>
            {/if}
        </div>
    </div>

    <!-- Upload Proof Modal -->
    {#if showUploadModal}
        <div
            class="fixed inset-0 z-50 flex items-end lg:items-center justify-center"
            onclick={() => (showUploadModal = false)}
        >
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div
                class="relative z-10 bg-white w-full lg:max-w-md rounded-t-3xl lg:rounded-2xl p-5"
                onclick={(e: any) => e.stopPropagation()}
            >
                <h3 class="font-bold text-slate-800 mb-4">Upload Bukti Pembayaran</h3>

                <div
                    class="border-2 border-dashed border-slate-300 rounded-xl p-6 text-center cursor-pointer hover:border-slate-400 transition"
                    onclick={() => (document.getElementById('proof-upload-input') as HTMLInputElement)?.click()}
                >
                    {#if proofPreview}
                        <img src={proofPreview} alt="Preview" class="max-h-48 mx-auto rounded-lg object-contain" />
                        <p class="text-xs text-slate-500 mt-2">Klik untuk ganti gambar</p>
                    {:else}
                        <i class="ti ti-photo text-4xl text-slate-300"></i>
                        <p class="text-sm text-slate-500 mt-2">Klik untuk memilih foto</p>
                        <p class="text-xs text-slate-400 mt-1">JPG, PNG, WEBP (maks. 5MB)</p>
                    {/if}
                </div>
                <input
                    id="proof-upload-input"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    onchange={handleFileChange}
                />

                <div class="flex gap-3 mt-4">
                    <button
                        onclick={() => (showUploadModal = false)}
                        class="flex-1 py-3 rounded-xl border-2 border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50 transition"
                    >
                        Batal
                    </button>
                    <button
                        onclick={uploadProof}
                        disabled={!proofFile || uploadingProof}
                        class="flex-1 py-3 rounded-xl font-bold text-white text-sm transition disabled:opacity-50"
                        style="background:{primary}"
                    >
                        {uploadingProof ? 'Mengunggah...' : 'Upload Sekarang'}
                    </button>
                </div>
            </div>
        </div>
    {/if}
</StorefrontLayout>
