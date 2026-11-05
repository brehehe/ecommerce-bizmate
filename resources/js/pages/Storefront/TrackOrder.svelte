<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';
    import { fade, slide } from 'svelte/transition';

    let {
        transaction = null,
        searchTrx = '',
        searchContact = '',
        errorMessage = null,
        statusLabels = {} as Record<string, string>,
        returnStatusLabels = {} as Record<string, string>,
        storeName = '',
        storeLogo = '',
        storeWhatsapp = '',
        biteshipEnabled = false,
    } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#0c4cb4',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#fa7315',
    );
    const storeSettings = $derived((page.props as any).settings || {});

    // svelte-ignore state_referenced_locally
    let inputTrx = $state(searchTrx || '');
    // svelte-ignore state_referenced_locally
    let inputContact = $state(searchContact || '');
    let isSearching = $state(false);

    // Upload proof states
    let showUploadModal = $state(false);
    let proofFile = $state<File | null>(null);
    let proofPreview = $state('');
    let uploadingProof = $state(false);

    // Countdown for unpaid transactions
    let countdownText = $state('');

    $effect(() => {
        if (
            transaction &&
            transaction.status === 'belum_bayar' &&
            transaction.payment_expires_at
        ) {
            const updateCountdown = () => {
                const now = new Date().getTime();
                const expiry = new Date(
                    transaction.payment_expires_at,
                ).getTime();
                const distance = expiry - now;

                if (distance <= 0) {
                    countdownText = 'Waktu pembayaran telah habis';
                    return;
                }

                const hours = Math.floor(distance / (1000 * 60 * 60));
                const minutes = Math.floor(
                    (distance % (1000 * 60 * 60)) / (1000 * 60),
                );
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                countdownText = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            };

            updateCountdown();
            const interval = setInterval(updateCountdown, 1000);
            return () => clearInterval(interval);
        }
    });

    function handleSearch(e: Event) {
        e.preventDefault();
        const trimmedTrx = inputTrx.trim();
        const trimmedContact = inputContact.trim();

        if (!trimmedTrx) {
            showToast('Silakan masukkan nomor pesanan Anda.', 'error');
            return;
        }

        isSearching = true;
        router.get(
            '/cek-pesanan',
            {
                trx: trimmedTrx,
                contact: trimmedContact,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => {
                    isSearching = false;
                },
            },
        );
    }

    function copyToClipboard(text: string, label = 'Teks') {
        if (!text) return;
        navigator.clipboard.writeText(text);
        showToast(`${label} berhasil disalin!`, 'success');
    }

    function formatRupiah(amount: number) {
        return 'Rp ' + Number(amount || 0).toLocaleString('id-ID');
    }

    function formatDate(dateStr: string) {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function handleFileChange(e: Event) {
        const input = e.target as HTMLInputElement;
        if (input.files?.[0]) {
            proofFile = input.files[0];
            proofPreview = URL.createObjectURL(proofFile);
        }
    }

    function uploadProof() {
        if (!proofFile || !transaction) return;
        uploadingProof = true;

        const form = new FormData();
        form.append('proof_image', proofFile);

        router.post(`/cek-pesanan/${transaction.id}/upload-proof`, form as any, {
            onSuccess: () => {
                showUploadModal = false;
                proofFile = null;
                proofPreview = '';
                showToast('Bukti pembayaran berhasil diunggah!', 'success');
            },
            onError: (errs) => {
                const msg = Object.values(errs)[0] || 'Gagal mengunggah bukti.';
                showToast(msg as string, 'error');
            },
            onFinish: () => {
                uploadingProof = false;
            },
        });
    }

    const waLink = $derived.by(() => {
        const rawPhone = storeWhatsapp || storeSettings.store_phone || '';
        if (!rawPhone || !transaction) return '#';
        let clean = rawPhone.replace(/\D/g, '');
        if (clean.startsWith('0')) clean = '62' + clean.slice(1);
        const text = encodeURIComponent(
            `Halo Admin ${storeName}, saya ingin menanyakan pesanan dengan Nomor: #${transaction.transaction_number}. Terima kasih.`,
        );
        return `https://wa.me/${clean}?text=${text}`;
    });

    const statusBadgeClass = $derived.by(() => {
        const st = transaction?.status;
        switch (st) {
            case 'belum_bayar':
                return 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-900';
            case 'menunggu':
                return 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-950/40 dark:text-blue-400 dark:border-blue-900';
            case 'diproses':
            case 'dikemas':
                return 'bg-indigo-100 text-indigo-800 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-400 dark:border-indigo-900';
            case 'out_for_pickup':
            case 'dikirim':
                return 'bg-cyan-100 text-cyan-800 border-cyan-200 dark:bg-cyan-950/40 dark:text-cyan-400 dark:border-cyan-900';
            case 'selesai':
                return 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-900';
            case 'batal':
                return 'bg-rose-100 text-rose-800 border-rose-200 dark:bg-rose-950/40 dark:text-rose-400 dark:border-rose-900';
            default:
                return 'bg-slate-100 text-slate-800 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700';
        }
    });
</script>

<svelte:head>
    <title>Cek & Lacak Pesanan | {storeName || 'Toko Kami'}</title>
</svelte:head>

<StorefrontLayout>
    <div class="min-h-screen bg-slate-50/70 dark:bg-slate-950 py-6 sm:py-10 px-3 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto space-y-6">

            <!-- Title & Hero Header -->
            <div class="text-center space-y-2">
                <div
                    class="inline-flex items-center justify-center w-12 h-12 rounded-2xl text-white shadow-md mx-auto"
                    style="background: linear-gradient(135deg, {primary}, {secondary});"
                >
                    <i class="ti ti-receipt-2 text-2xl"></i>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                    Cek & Lacak Pesanan
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 max-w-lg mx-auto">
                    Masukkan nomor pesanan serta email atau nomor WhatsApp Anda untuk memantau status pembayaran dan pengiriman barang secara real-time.
                </p>
            </div>

            <!-- Search Card -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-7 shadow-sm border border-slate-100 dark:border-slate-800">
                <form onsubmit={handleSearch} class="space-y-4">
                    <div class="space-y-1.5">
                        <label for="order-number-input" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                            Nomor Pesanan / Kode Transaksi <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="ti ti-hash absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input
                                id="order-number-input"
                                type="text"
                                bind:value={inputTrx}
                                placeholder="Contoh: TRX-20261102-00001"
                                class="w-full pl-10 pr-4 py-3 text-sm font-semibold rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2"
                                style="--tw-ring-color: {primary};"
                                required
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="contact-input" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                            Email atau No. WhatsApp Pemesan <span class="text-slate-400 font-normal lowercase">(untuk verifikasi)</span>
                        </label>
                        <div class="relative">
                            <i class="ti ti-user-check absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                            <input
                                id="contact-input"
                                type="text"
                                bind:value={inputContact}
                                placeholder="Email atau nomor WhatsApp saat checkout"
                                class="w-full pl-10 pr-4 py-3 text-sm font-medium rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2"
                                style="--tw-ring-color: {primary};"
                            />
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={isSearching}
                        class="w-full py-3.5 px-6 rounded-2xl font-bold text-sm text-white shadow-lg transition-transform active:scale-[0.99] flex items-center justify-center gap-2"
                        style="background: linear-gradient(135deg, {primary}, {secondary});"
                    >
                        {#if isSearching}
                            <i class="ti ti-loader-2 animate-spin text-lg"></i>
                            <span>Mencari Pesanan...</span>
                        {:else}
                            <i class="ti ti-search text-lg"></i>
                            <span>Lacak & Tampilkan Pesanan</span>
                        {/if}
                    </button>
                </form>
            </div>

            <!-- Error Banner -->
            {#if errorMessage}
                <div
                    transition:slide={{ duration: 150 }}
                    class="bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/50 rounded-2xl p-4 flex items-start gap-3"
                >
                    <i class="ti ti-alert-triangle text-xl text-rose-500 shrink-0 mt-0.5"></i>
                    <div class="text-sm text-rose-700 dark:text-rose-300 font-medium">
                        <p class="font-bold mb-0.5">Pesanan Tidak Ditemukan / Butuh Verifikasi</p>
                        <p>{errorMessage}</p>
                    </div>
                </div>
            {/if}

            <!-- Transaction Detail Result -->
            {#if transaction}
                <div transition:fade={{ duration: 150 }} class="space-y-5">

                    <!-- Status Header Card -->
                    <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-6 shadow-sm border border-slate-100 dark:border-slate-800 space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-4">
                            <div>
                                <span class="text-xs font-semibold text-slate-400 uppercase tracking-widest block">Nomor Pesanan</span>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-lg sm:text-xl font-black text-slate-900 dark:text-white">
                                        #{transaction.transaction_number}
                                    </span>
                                    <button
                                        type="button"
                                        onclick={() => copyToClipboard(transaction.transaction_number, 'Nomor pesanan')}
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                        title="Salin nomor pesanan"
                                    >
                                        <i class="ti ti-copy text-sm"></i>
                                    </button>
                                </div>
                                <span class="text-xs text-slate-400">
                                    Dibuat pada {formatDate(transaction.created_at)}
                                </span>
                            </div>

                            <div class="flex flex-col items-end gap-1">
                                <span class="px-3.5 py-1 rounded-full text-xs font-extrabold uppercase tracking-wide border {statusBadgeClass}">
                                    {statusLabels[transaction.status] ?? transaction.status}
                                </span>
                                {#if countdownText}
                                    <div class="text-xs font-bold text-amber-600 dark:text-amber-400 flex items-center gap-1">
                                        <i class="ti ti-clock"></i>
                                        <span>Batas Bayar: {countdownText}</span>
                                    </div>
                                {/if}
                            </div>
                        </div>

                        <!-- Customer Info -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs pt-1">
                            <div class="space-y-1">
                                <span class="text-slate-400 font-semibold uppercase tracking-wider block">Pemesan</span>
                                <p class="font-bold text-slate-800 dark:text-slate-100 text-sm">
                                    {transaction.customer_name || '—'}
                                </p>
                                <p class="text-slate-500 dark:text-slate-400">
                                    {transaction.customer_email || '—'} • {transaction.customer_phone || '—'}
                                </p>
                            </div>

                            {#if transaction.customer_address}
                                <div class="space-y-1">
                                    <span class="text-slate-400 font-semibold uppercase tracking-wider block">Alamat Pengiriman</span>
                                    <p class="font-bold text-slate-800 dark:text-slate-100">
                                        {transaction.customer_address.receiver_name} ({transaction.customer_address.phone_number})
                                    </p>
                                    <p class="text-slate-500 dark:text-slate-400 leading-relaxed">
                                        {transaction.customer_address.full_address}, {transaction.customer_address.district_name || ''}, {transaction.customer_address.regency_name || ''}, {transaction.customer_address.province_name || ''} {transaction.customer_address.postal_code || ''}
                                    </p>
                                </div>
                            {:else if transaction.shipping_courier === 'self_pickup'}
                                <div class="space-y-1">
                                    <span class="text-slate-400 font-semibold uppercase tracking-wider block">Metode Pengambilan</span>
                                    <p class="font-bold text-emerald-600 dark:text-emerald-400">
                                        Ambil di Toko Langsung (Self Pickup)
                                    </p>
                                    {#if transaction.pickup_at}
                                        <p class="text-slate-500 dark:text-slate-400">
                                            Jadwal: {formatDate(transaction.pickup_at)}
                                        </p>
                                    {/if}
                                </div>
                            {/if}
                        </div>
                    </div>

                    <!-- Payment Instruction Card (For Unpaid / Manual Transfer) -->
                    {#if transaction.status === 'belum_bayar' || transaction.status === 'menunggu'}
                        <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-6 shadow-sm border border-slate-100 dark:border-slate-800 space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                                <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2 text-base">
                                    <i class="ti ti-wallet text-xl" style="color: {primary};"></i>
                                    Informasi Pembayaran
                                </h3>
                                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                                    {transaction.payment_method?.name || 'Metode Pembayaran'}
                                </span>
                            </div>

                            <div class="space-y-3">
                                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 flex items-center justify-between flex-wrap gap-2">
                                    <div>
                                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Total Tagihan</span>
                                        <span class="text-2xl font-black text-slate-900 dark:text-white" style="color: {primary};">
                                            {formatRupiah(transaction.grand_total)}
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        onclick={() => copyToClipboard(String(Math.round(transaction.grand_total)), 'Nominal tagihan')}
                                        class="px-3 py-1.5 rounded-xl text-xs font-bold bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 hover:bg-slate-100 transition shadow-sm"
                                    >
                                        <i class="ti ti-copy mr-1"></i> Salin Jumlah
                                    </button>
                                </div>

                                <!-- Manual Bank Transfer Instruction -->
                                {#if transaction.payment_method?.type === 'manual'}
                                    <div class="p-4 rounded-2xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50 space-y-2 text-xs text-amber-900 dark:text-amber-200">
                                        <p class="font-bold flex items-center gap-1.5 text-sm">
                                            <i class="ti ti-building-bank"></i>
                                            Transfer ke Rekening Toko:
                                        </p>
                                        <div class="bg-white dark:bg-slate-900 p-3 rounded-xl border border-amber-200 dark:border-amber-900 flex items-center justify-between">
                                            <div>
                                                <p class="font-bold text-slate-900 dark:text-white text-sm">
                                                    {transaction.payment_method?.account_number || '—'}
                                                </p>
                                                <p class="text-slate-500 dark:text-slate-400">
                                                    a.n. {transaction.payment_method?.account_holder || storeName}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                onclick={() => copyToClipboard(transaction.payment_method?.account_number || '', 'Nomor rekening')}
                                                class="px-2.5 py-1 text-xs font-bold text-white rounded-lg"
                                                style="background: {primary};"
                                            >
                                                Salin
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Proof Status & Upload Button -->
                                    <div class="pt-1">
                                        {#if transaction.status === 'menunggu'}
                                            <div class="p-3.5 rounded-2xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-900 flex items-center gap-3 text-xs text-blue-800 dark:text-blue-300 font-medium">
                                                <i class="ti ti-clock-check text-xl text-blue-600 dark:text-blue-400 shrink-0"></i>
                                                <div>
                                                    <p class="font-bold">Bukti Transfer Berhasil Dikirim</p>
                                                    <p>Pembayaran Anda sedang dalam proses verifikasi oleh tim admin.</p>
                                                </div>
                                            </div>
                                        {:else}
                                            <button
                                                type="button"
                                                onclick={() => (showUploadModal = true)}
                                                class="w-full py-3 px-4 rounded-2xl font-bold text-sm text-white shadow transition flex items-center justify-center gap-2"
                                                style="background: {secondary};"
                                            >
                                                <i class="ti ti-upload text-base"></i>
                                                Upload Bukti Transfer Pembayaran
                                            </button>
                                        {/if}
                                    </div>
                                {/if}
                            </div>
                        </div>
                    {/if}

                    <!-- Shipping / Resi Info Card -->
                    {#if transaction.shipping_courier !== 'self_pickup' && transaction.shipping_courier !== 'digital'}
                        <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-6 shadow-sm border border-slate-100 dark:border-slate-800 space-y-3">
                            <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2 text-base border-b border-slate-100 dark:border-slate-800 pb-3">
                                <i class="ti ti-truck-delivery text-xl" style="color: {primary};"></i>
                                Informasi Pengiriman & Resi
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
                                    <span class="text-slate-400 font-semibold uppercase tracking-wider block">Kurir & Layanan</span>
                                    <p class="font-bold text-slate-800 dark:text-slate-100 text-sm mt-0.5 uppercase">
                                        {transaction.shipping_courier} {transaction.shipping_service ? `— ${transaction.shipping_service}` : ''}
                                    </p>
                                    {#if transaction.shipping_etd}
                                        <p class="text-slate-500 dark:text-slate-400 mt-0.5">
                                            Estimasi tiba: {transaction.shipping_etd} hari
                                        </p>
                                    {/if}
                                </div>

                                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
                                    <span class="text-slate-400 font-semibold uppercase tracking-wider block">Nomor Resi</span>
                                    {#if transaction.tracking_number}
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="font-black text-slate-900 dark:text-white text-sm tracking-wide">
                                                {transaction.tracking_number}
                                            </span>
                                            <button
                                                type="button"
                                                onclick={() => copyToClipboard(transaction.tracking_number, 'Nomor resi')}
                                                class="p-1 rounded text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                                title="Salin resi"
                                            >
                                                <i class="ti ti-copy"></i>
                                            </button>
                                        </div>
                                    {:else}
                                        <p class="text-slate-400 italic text-xs mt-1">
                                            Nomor resi akan muncul setelah paket diserahkan ke kurir.
                                        </p>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    {/if}

                    <!-- Items List Card -->
                    <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-6 shadow-sm border border-slate-100 dark:border-slate-800 space-y-4">
                        <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2 text-base border-b border-slate-100 dark:border-slate-800 pb-3">
                            <i class="ti ti-package text-xl" style="color: {primary};"></i>
                            Rincian Produk Dipesan ({transaction.items?.length || 0})
                        </h3>

                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            {#each transaction.items || [] as item}
                                <div class="py-3 flex items-start gap-3">
                                    {#if item.product?.image}
                                        <img
                                            src={item.product.image.startsWith('http') ? item.product.image : `/storage/${item.product.image}`}
                                            alt={item.product_name}
                                            class="w-14 h-14 rounded-xl object-cover shrink-0 border border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800"
                                        />
                                    {:else}
                                        <div class="w-14 h-14 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center shrink-0 text-slate-400">
                                            <i class="ti ti-photo text-xl"></i>
                                        </div>
                                    {/if}

                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold text-sm text-slate-800 dark:text-slate-100 line-clamp-2">
                                            {item.product_name}
                                        </p>
                                        {#if item.product_variant_name}
                                            <span class="inline-block px-2 py-0.5 text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-md mt-1">
                                                Varian: {item.product_variant_name}
                                            </span>
                                        {/if}
                                        <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 mt-1">
                                            <span>{item.quantity} x {formatRupiah(item.price)}</span>
                                            <span class="font-bold text-slate-900 dark:text-white">
                                                {formatRupiah(item.subtotal || item.price * item.quantity)}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            {/each}
                        </div>

                        <!-- Price Breakdown -->
                        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-1.5 text-xs text-slate-600 dark:text-slate-300">
                            <div class="flex justify-between">
                                <span>Subtotal Produk</span>
                                <span class="font-semibold">{formatRupiah(transaction.subtotal)}</span>
                            </div>

                            {#if Number(transaction.discount_amount) > 0}
                                <div class="flex justify-between text-emerald-600 dark:text-emerald-400">
                                    <span>Diskon Voucher</span>
                                    <span>- {formatRupiah(transaction.discount_amount)}</span>
                                </div>
                            {/if}

                            <div class="flex justify-between">
                                <span>Biaya Pengiriman (Ongkir)</span>
                                <span class="font-semibold">{formatRupiah(transaction.shipping_fee)}</span>
                            </div>

                            {#if Number(transaction.shipping_discount) > 0}
                                <div class="flex justify-between text-emerald-600 dark:text-emerald-400">
                                    <span>Diskon Ongkir</span>
                                    <span>- {formatRupiah(transaction.shipping_discount)}</span>
                                </div>
                            {/if}

                            {#if Number(transaction.admin_fee) > 0}
                                <div class="flex justify-between">
                                    <span>Biaya Layanan / Admin</span>
                                    <span>{formatRupiah(transaction.admin_fee)}</span>
                                </div>
                            {/if}

                            {#if Number(transaction.application_fee) > 0}
                                <div class="flex justify-between">
                                    <span>Biaya Aplikasi</span>
                                    <span>{formatRupiah(transaction.application_fee)}</span>
                                </div>
                            {/if}

                            <div class="flex justify-between items-center pt-2 border-t border-slate-100 dark:border-slate-800 text-sm font-black text-slate-900 dark:text-white">
                                <span>Total Pembayaran</span>
                                <span class="text-base" style="color: {primary};">
                                    {formatRupiah(transaction.grand_total)}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Action Bar -->
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                        <Link
                            href="/"
                            class="px-5 py-3 rounded-2xl text-xs font-bold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:bg-slate-50 transition flex items-center gap-2"
                        >
                            <i class="ti ti-arrow-left"></i>
                            Kembali Belanja
                        </Link>

                        <div class="flex items-center gap-2">
                            {#if waLink !== '#'}
                                <a
                                    href={waLink}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="px-5 py-3 rounded-2xl text-xs font-bold text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 hover:bg-emerald-100 transition flex items-center gap-2"
                                >
                                    <i class="ti ti-brand-whatsapp text-base"></i>
                                    Hubungi Penjual
                                </a>
                            {/if}
                        </div>
                    </div>

                </div>
            {/if}

        </div>
    </div>

    <!-- Upload Proof Modal -->
    {#if showUploadModal}
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" transition:fade={{ duration: 150 }}>
            <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 dark:border-slate-800 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="font-bold text-slate-900 dark:text-white text-base">Upload Bukti Transfer</h3>
                    <button
                        type="button"
                        onclick={() => (showUploadModal = false)}
                        class="p-1 rounded-full text-slate-400 hover:text-slate-600"
                    >
                        <i class="ti ti-x text-lg"></i>
                    </button>
                </div>

                <div class="space-y-3">
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        Unggah foto atau tangkapan layar (screenshot) bukti transfer Anda untuk pesanan #{transaction?.transaction_number}. Format JPG/PNG maksimal 5MB.
                    </p>

                    {#if proofPreview}
                        <div class="relative rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-700">
                            <img src={proofPreview} alt="Preview Bukti" class="w-full h-48 object-cover" />
                            <button
                                type="button"
                                onclick={() => {
                                    proofFile = null;
                                    proofPreview = '';
                                }}
                                class="absolute top-2 right-2 p-1.5 rounded-full bg-black/60 text-white hover:bg-black/80"
                            >
                                <i class="ti ti-trash"></i>
                            </button>
                        </div>
                    {:else}
                        <label class="border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-2xl p-6 flex flex-col items-center justify-center cursor-pointer hover:border-slate-400 transition bg-slate-50 dark:bg-slate-800/50">
                            <i class="ti ti-cloud-upload text-3xl text-slate-400 mb-2"></i>
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Pilih Foto Bukti Transfer</span>
                            <span class="text-[10px] text-slate-400 mt-1">PNG, JPG atau WEBP (Maks 5MB)</span>
                            <input type="file" accept="image/*" onchange={handleFileChange} class="hidden" />
                        </label>
                    {/if}
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button
                        type="button"
                        onclick={() => (showUploadModal = false)}
                        class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        onclick={uploadProof}
                        disabled={!proofFile || uploadingProof}
                        class="px-5 py-2.5 rounded-xl text-xs font-bold text-white shadow disabled:opacity-50 flex items-center gap-1.5"
                        style="background: {primary};"
                    >
                        {#if uploadingProof}
                            <i class="ti ti-loader-2 animate-spin"></i>
                            Mengunggah...
                        {:else}
                            Kirim Bukti Pembayaran
                        {/if}
                    </button>
                </div>
            </div>
        </div>
    {/if}
</StorefrontLayout>
