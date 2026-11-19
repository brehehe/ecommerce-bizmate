<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import OrderItemModifiers from '@/components/Storefront/OrderItemModifiers.svelte';
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
        isFoodPick = false,
        foodpickSettings = {} as Record<string, any>,
    } = $props();

    const isFoodOrder = $derived(
        Boolean(
            isFoodPick ||
            transaction?.resto_id ||
            transaction?.kitchen_status ||
            transaction?.fulfillment_type ||
            transaction?.table_number ||
            transaction?.scheduled_time_slot
        )
    );

    const isCodMethod = $derived(
        Boolean(
            transaction?.payment_method?.name?.toLowerCase().includes('cod') ||
            transaction?.payment_method?.name?.toLowerCase().includes('bayar di tempat')
        )
    );

    const kitchenStep = $derived.by(() => {
        if (!transaction) return 0;
        if (transaction.status === 'batal') return -1;
        if (transaction.status === 'belum_bayar' || transaction.status === 'menunggu') return 0;

        const ks = transaction.kitchen_status;
        if (ks === 'selesai' || transaction.status === 'selesai') return 4;
        if (ks === 'siap_diambil' || transaction.status === 'ready_for_pickup' || transaction.status === 'dikemas') return 3;
        if (ks === 'dimasak') return 2;
        return 1; // pesanan_masuk / diproses
    });

    const culinarySteps = [
        {
            step: 1,
            title: 'Pesanan Masuk',
            subtitle: 'Diterima Dapur Resto',
            icon: 'ti-receipt-2',
        },
        {
            step: 2,
            title: 'Sedang Dimasak',
            subtitle: 'Koki Mengolah Hidangan',
            icon: 'ti-flame',
        },
        {
            step: 3,
            title: 'Siap Diambil / Diantar',
            subtitle: 'Makanan Telah Matang',
            icon: 'ti-bell-ringing',
        },
        {
            step: 4,
            title: 'Selesai',
            subtitle: 'Selamat Menikmati',
            icon: 'ti-circle-check',
        },
    ];

    const culinaryStatusMessage = $derived.by(() => {
        if (kitchenStep === -1) {
            return {
                title: 'Pesanan Dibatalkan',
                desc: 'Pesanan kuliner ini telah dibatalkan.',
                theme: 'rose',
                icon: 'ti-x',
            };
        }
        if (kitchenStep === 0) {
            return {
                title: 'Menunggu Pembayaran Dikonfirmasi',
                desc: 'Silakan selesaikan pembayaran agar pesanan segera masuk ke antrean koki resto.',
                theme: 'amber',
                icon: 'ti-wallet',
            };
        }
        if (kitchenStep === 1) {
            return {
                title: 'Pesanan Diterima Dapur!',
                desc: 'Pesanan telah diterima oleh dapur resto dan sedang disiapkan ke antrean koki.',
                theme: 'blue',
                icon: 'ti-receipt-2',
            };
        }
        if (kitchenStep === 2) {
            return {
                title: 'Koki Sedang Memasak Hidangan Anda!',
                desc: 'Hidangan Anda sedang dimasak hangat oleh koki resto.',
                theme: 'orange',
                icon: 'ti-flame',
            };
        }
        if (kitchenStep === 3) {
            const isDelivery = transaction?.fulfillment_type === 'store_courier' || transaction?.shipping_courier === 'store_courier';
            return {
                title: isDelivery ? 'Pesanan Matang & Sedang Diantar!' : 'Pesanan Siap Diambil di Resto!',
                desc: isDelivery
                    ? 'Makanan hangat telah selesai dimasak dan sedang dalam perjalanan pengantaran kurir resto.'
                    : 'Makanan telah selesai dimasak & siap diserahkan. Tunjukkan kode pesanan ke kasir resto.',
                theme: 'emerald',
                icon: isDelivery ? 'ti-moped' : 'ti-bell-ringing',
            };
        }
        return {
            title: 'Pesanan Telah Selesai Diserahkan!',
            desc: 'Makanan telah diterima dengan baik. Selamat menikmati hidangan lezat Anda!',
            theme: 'emerald',
            icon: 'ti-circle-check',
        };
    });

    const restoWhatsAppLink = $derived.by(() => {
        const phone = transaction?.resto?.phone_number || storeWhatsapp || '';
        if (!phone || !transaction) return null;
        let clean = phone.replace(/\D/g, '');
        if (clean.startsWith('0')) clean = '62' + clean.slice(1);
        const restoName = transaction.restoProfile?.resto_name || transaction.resto?.store_name || storeName;
        const text = encodeURIComponent(
            `Halo Resto ${restoName}, saya ingin menanyakan pesanan makanan #${transaction.transaction_number}. Terima kasih.`
        );
        return `https://wa.me/${clean}?text=${text}`;
    });

    const restoMapLink = $derived.by(() => {
        const rp = transaction?.restoProfile;
        if (!rp) return null;
        if (rp.latitude && rp.longitude) {
            return `https://www.google.com/maps/search/?api=1&query=${rp.latitude},${rp.longitude}`;
        }
        if (rp.address) {
            return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(rp.address)}`;
        }
        return null;
    });

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

    // Rating Toko / Resto State
    let selectedRating = $state(5);
    let hoverRating = $state(0);
    let reviewComment = $state('');
    let submittingReview = $state(false);

    function submitStoreReview() {
        if (!transaction) return;
        if (selectedRating < 1 || selectedRating > 5) {
            showToast('Silakan pilih rating 1-5 bintang.', 'error');
            return;
        }

        submittingReview = true;
        router.post(`/cek-pesanan/${transaction.id}/review`, {
            rating: selectedRating,
            comment: reviewComment,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                showToast('Ulasan dan rating berhasil dikirim!', 'success');
            },
            onError: (errs) => {
                const msg = Object.values(errs)[0] || 'Gagal mengirim ulasan.';
                showToast(msg as string, 'error');
            },
            onFinish: () => {
                submittingReview = false;
            },
        });
    }

    // Countdown for unpaid transactions
    let countdownText = $state('');
    let checkingStatus = $state(false);

    function checkPaymentStatus() {
        if (!transaction) return;
        checkingStatus = true;
        router.reload({
            only: ['transaction'],
            preserveScroll: true,
            onFinish: () => {
                checkingStatus = false;
            },
        });
    }

    // Auto-poll payment status every 6 seconds if status is belum_bayar or menunggu
    $effect(() => {
        if (
            transaction &&
            (transaction.status === 'belum_bayar' || transaction.status === 'menunggu')
        ) {
            const pollTimer = setInterval(() => {
                router.reload({
                    only: ['transaction'],
                    preserveScroll: true,
                });
            }, 6000);
            return () => clearInterval(pollTimer);
        }
    });

    // Auto-poll kitchen & order status every 8 seconds for active food orders
    $effect(() => {
        if (
            transaction &&
            isFoodOrder &&
            transaction.kitchen_status !== 'selesai' &&
            transaction.status !== 'selesai' &&
            transaction.status !== 'batal'
        ) {
            const pollTimer = setInterval(() => {
                router.reload({
                    only: ['transaction'],
                    preserveScroll: true,
                });
            }, 8000);
            return () => clearInterval(pollTimer);
        }
    });

    const latestPayment = $derived(
        transaction?.payment ??
            (transaction?.payments && transaction.payments.length > 0
                ? transaction.payments[transaction.payments.length - 1]
                : null)
    );

    const isQris = $derived(
        transaction?.payment_method?.name?.toLowerCase().includes('qris') ||
        transaction?.payment_method?.type === 'waris_qris' ||
        transaction?.payment_method?.settings?.gateway === 'waris'
    );

    const qrisData = $derived.by(() => {
        if (!isQris || !latestPayment || !latestPayment.gateway_response)
            return null;
        try {
            let resp = latestPayment.gateway_response;
            while (typeof resp === 'string') {
                try {
                    resp = JSON.parse(resp);
                } catch {
                    break;
                }
            }
            const rawData = resp?.raw?.data;
            const qrStr = resp?.qr_string ?? resp?.qris_string ?? rawData?.qr_string ?? '';
            let qrImg = resp?.qr_image_data_uri ?? resp?.qr_image_url ?? resp?.qris_image ?? rawData?.qr_image_data_uri ?? rawData?.qr_image_url ?? '';
            if (!qrImg && qrStr) {
                qrImg = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrStr)}`;
            }
            const apiError = resp?.error || (latestPayment?.gateway_status === 'FAILED_API' ? 'Gagal memuat QRIS dari gateway.' : null);
            return {
                image: qrImg,
                string: qrStr,
                nmid: resp?.qris_source?.nmid ?? rawData?.nmid ?? '',
                merchant: resp?.qris_source?.label ?? rawData?.merchant_name ?? '',
                error: apiError,
            };
        } catch {
            return null;
        }
    });

    function downloadQrisImage() {
        if (!qrisData?.image) return;
        const link = document.createElement('a');
        link.href = qrisData.image;
        link.download = `QRIS-${transaction.transaction_number}.png`;
        link.click();
    }

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
    <div class="min-h-screen bg-slate-50/70 dark:bg-slate-950 py-6 sm:py-10 px-4 sm:px-6 lg:px-8">
        <div class="max-w-6xl mx-auto space-y-6">

            {#if !transaction}
                <!-- Initial State: Centered Hero & Search Card -->
                <div class="max-w-xl mx-auto space-y-6 py-6 sm:py-12">
                    <div class="text-center space-y-2">
                        <div
                            class="inline-flex items-center justify-center w-12 h-12 rounded-2xl text-white bg-slate-900 shadow-sm mx-auto"
                        >
                            <i class="ti ti-receipt-2 text-2xl"></i>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                            Cek & Lacak Pesanan
                        </h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                            Masukkan nomor pesanan serta email atau nomor WhatsApp Anda untuk memantau status pesanan dan pengiriman secara real-time.
                        </p>
                    </div>

                    <!-- Search Card -->
                    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-2xs border border-slate-200/80 dark:border-slate-800">
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
                                        placeholder="Contoh: TRX-20261117-00001"
                                        class="w-full pl-10 pr-4 py-3 text-sm font-semibold rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900"
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
                                        class="w-full pl-10 pr-4 py-3 text-sm font-medium rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900"
                                    />
                                </div>
                            </div>

                            <button
                                type="submit"
                                disabled={isSearching}
                                class="w-full py-3.5 px-6 rounded-2xl font-bold text-sm text-white bg-slate-900 hover:bg-slate-800 shadow-sm transition active:scale-[0.99] flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
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
                </div>
            {:else}
                <!-- Active Transaction View: Matches max-w-6xl navbar width -->
                <!-- Compact search bar for searching another order -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-3.5 sm:p-4 border border-slate-200/80 dark:border-slate-800 shadow-2xs">
                    <form onsubmit={handleSearch} class="flex flex-col sm:flex-row items-center gap-2.5">
                        <div class="relative flex-1 w-full">
                            <i class="ti ti-hash absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input
                                type="text"
                                bind:value={inputTrx}
                                placeholder="Lacak nomor pesanan lain (contoh: TRX-20261117-...)"
                                class="w-full pl-9 pr-3 py-2 text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-slate-900"
                                required
                            />
                        </div>
                        <div class="relative w-full sm:w-64">
                            <i class="ti ti-user-check absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input
                                type="text"
                                bind:value={inputContact}
                                placeholder="Email/No. WA pemesan (opsional)"
                                class="w-full pl-9 pr-3 py-2 text-xs font-medium rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-slate-900"
                            />
                        </div>
                        <button
                            type="submit"
                            disabled={isSearching}
                            class="w-full sm:w-auto px-5 py-2 rounded-xl text-xs font-bold text-white bg-slate-900 hover:bg-slate-800 active:scale-95 transition flex items-center justify-center gap-1.5 shrink-0 cursor-pointer shadow-xs disabled:opacity-50"
                        >
                            {#if isSearching}
                                <i class="ti ti-loader-2 animate-spin text-sm"></i>
                                <span>Mencari...</span>
                            {:else}
                                <i class="ti ti-search text-sm"></i>
                                <span>Cari</span>
                            {/if}
                        </button>
                    </form>
                </div>

                <!-- Error Banner if any -->
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

                <!-- Responsive 2-Column Grid (Left: Timeline & Status, Right: Payment & Items) -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    <!-- ════════════════════════════════════════════════════════
                         LEFT COLUMN (7 cols): Order Status, Kitchen Progress, Rating
                         ════════════════════════════════════════════════════════ -->
                    <div class="lg:col-span-7 space-y-6">

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

                            <div class="flex flex-col items-end gap-1.5">
                                <div class="flex items-center gap-2">
                                    <a
                                        href={`/transactions/${transaction.id}/print-thermal?format=58mm&type=cashier&contact=${encodeURIComponent(inputContact || transaction.customer_phone || transaction.customer_email || '')}`}
                                        target="_blank"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-[11px] font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                                        title="Cetak Bukti Struk Thermal"
                                    >
                                        <i class="ti ti-printer text-xs text-slate-500"></i>
                                        <span>Struk</span>
                                    </a>
                                    <span class="px-3.5 py-1 rounded-full text-xs font-extrabold uppercase tracking-wide border {statusBadgeClass}">
                                        {statusLabels[transaction.status] ?? transaction.status}
                                    </span>
                                </div>
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

                    <!-- Culinary Live Kitchen & Pickup Tracking Card -->
                    {#if isFoodOrder}
                        <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-7 shadow-sm border border-slate-100 dark:border-slate-800 space-y-6">
                            <!-- Header with Food Badge & Live Indicator -->
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-10 h-10 rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl shrink-0">
                                        <i class="ti ti-tools-kitchen-2"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-black text-slate-900 dark:text-white text-base flex items-center gap-2">
                                            Status Dapur Resto Realtime
                                            {#if kitchenStep > 0 && kitchenStep < 4}
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200/50 dark:border-emerald-900/50">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                    Live
                                                </span>
                                            {/if}
                                        </h3>
                                        <p class="text-xs text-slate-400">
                                            {transaction.restoProfile?.resto_name || transaction.resto?.store_name || storeName}
                                            {#if transaction.restoProfile?.culinary_category}
                                                • {transaction.restoProfile.culinary_category}
                                            {/if}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onclick={checkPaymentStatus}
                                        disabled={checkingStatus}
                                        class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition flex items-center gap-1.5 border border-slate-200 dark:border-slate-700"
                                        title="Perbarui status dapur"
                                    >
                                        <i class="ti ti-refresh text-xs {checkingStatus ? 'animate-spin' : ''}"></i>
                                        <span>Perbarui</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Live Culinary Status Banner -->
                            <div class="p-4 rounded-2xl border transition-all {
                                culinaryStatusMessage.theme === 'rose'
                                    ? 'bg-rose-50/80 dark:bg-rose-950/30 border-rose-200 dark:border-rose-900 text-rose-900 dark:text-rose-200'
                                    : culinaryStatusMessage.theme === 'amber'
                                      ? 'bg-amber-50/80 dark:bg-amber-950/30 border-amber-200 dark:border-amber-900 text-amber-900 dark:text-amber-200'
                                      : culinaryStatusMessage.theme === 'orange'
                                        ? 'bg-orange-50/80 dark:bg-orange-950/30 border-orange-200 dark:border-orange-900 text-orange-900 dark:text-orange-200'
                                        : culinaryStatusMessage.theme === 'blue'
                                          ? 'bg-blue-50/80 dark:bg-blue-950/30 border-blue-200 dark:border-blue-900 text-blue-900 dark:text-blue-200'
                                          : 'bg-emerald-50/80 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-900 text-emerald-900 dark:text-emerald-200'
                            }">
                                <div class="flex items-start gap-3">
                                    <div class="p-2 rounded-xl bg-white/80 dark:bg-slate-900/80 shadow-sm shrink-0 text-xl {
                                        kitchenStep === 2 ? 'animate-bounce text-orange-500' : ''
                                    }">
                                        <i class={`ti ${culinaryStatusMessage.icon}`}></i>
                                    </div>
                                    <div class="space-y-0.5">
                                        <h4 class="font-bold text-sm">
                                            {culinaryStatusMessage.title}
                                        </h4>
                                        <p class="text-xs opacity-90 leading-relaxed">
                                            {culinaryStatusMessage.desc}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- 4-Step Cooking Progress Stepper -->
                            <div class="relative pt-2 pb-1">
                                <!-- Progress line background -->
                                <div class="absolute top-7 left-8 right-8 h-1 bg-slate-100 dark:bg-slate-800 -z-0"></div>
                                <!-- Progress line filled -->
                                <div
                                    class="absolute top-7 left-8 h-1 transition-all duration-500 -z-0"
                                    style="width: {kitchenStep <= 1 ? '0%' : kitchenStep === 2 ? '33%' : kitchenStep === 3 ? '66%' : 'calc(100% - 4rem)'}; background: {primary};"
                                ></div>

                                <div class="grid grid-cols-4 gap-2 relative z-10 text-center">
                                    {#each culinarySteps as step}
                                        {@const isDone = kitchenStep > step.step || (kitchenStep === 4 && step.step === 4)}
                                        {@const isCurrent = kitchenStep === step.step}
                                        <div class="flex flex-col items-center space-y-1.5">
                                            <div
                                                class="w-11 h-11 rounded-2xl flex items-center justify-center text-base transition-all duration-300 shadow-sm {
                                                    isCurrent
                                                        ? 'ring-4 ring-offset-2 ring-primary scale-110 text-white font-bold'
                                                        : isDone
                                                          ? 'bg-emerald-500 text-white'
                                                          : 'bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500'
                                                }"
                                                style={isCurrent ? `background: ${primary}; --tw-ring-color: ${primary}33;` : ''}
                                            >
                                                {#if isDone && !isCurrent}
                                                    <i class="ti ti-check text-lg"></i>
                                                {:else}
                                                    <i class={`ti ${step.icon}`}></i>
                                                {/if}
                                            </div>
                                            <div>
                                                <p class="text-xs font-bold leading-tight {
                                                    isCurrent
                                                        ? 'text-slate-900 dark:text-white font-black'
                                                        : isDone
                                                          ? 'text-emerald-600 dark:text-emerald-400 font-semibold'
                                                          : 'text-slate-400 dark:text-slate-500'
                                                }">
                                                    {step.title}
                                                </p>
                                                <p class="text-[10px] text-slate-400 hidden sm:block mt-0.5">
                                                    {step.subtitle}
                                                </p>
                                            </div>
                                        </div>
                                    {/each}
                                </div>
                            </div>

                            <!-- Fulfillment & Pickup Details Cards -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 text-xs">
                                <!-- Mode & Kode Kasir -->
                                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-400 font-semibold uppercase tracking-wider block">Mode Pemenuhan</span>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wide bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-900/50">
                                            {#if transaction.table_number || transaction.pickup_type === 'dine_in'}
                                                Dine-In • Meja #{transaction.table_number || '1'}
                                            {:else if transaction.fulfillment_type === 'store_courier' || transaction.shipping_courier === 'store_courier'}
                                                Kurir Resto (Delivery)
                                            {:else}
                                                Ambil Sendiri (Takeaway)
                                            {/if}
                                        </span>
                                    </div>

                                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/80 dark:border-slate-700/80 flex items-center justify-between">
                                        <div>
                                            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-widest block">Kode Kasir / Pengambilan</span>
                                            <span class="font-black text-slate-900 dark:text-white text-base tracking-wider">
                                                #{transaction.transaction_number.slice(-8).toUpperCase()}
                                            </span>
                                        </div>
                                        <button
                                            type="button"
                                            onclick={() => copyToClipboard(transaction.transaction_number.slice(-8).toUpperCase(), 'Kode kasir')}
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                            title="Salin kode kasir"
                                        >
                                            <i class="ti ti-copy text-sm"></i>
                                        </button>
                                    </div>

                                    {#if transaction.scheduled_time_slot || transaction.scheduled_date}
                                        <div class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300 font-medium text-xs pt-0.5">
                                            <i class="ti ti-clock text-amber-600 dark:text-amber-400 text-sm"></i>
                                            <span>
                                                Jadwal: <strong>{transaction.scheduled_date || 'Hari Ini'}</strong> • Pukul <strong>{transaction.scheduled_time_slot || 'Paling Cepat'}</strong>
                                            </span>
                                        </div>
                                    {/if}
                                </div>

                                <!-- Resto Info & Actions -->
                                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 space-y-2.5">
                                    <span class="text-slate-400 font-semibold uppercase tracking-wider block">Informasi Dapur Resto</span>
                                    <div>
                                        <p class="font-bold text-slate-800 dark:text-slate-100 text-sm">
                                            {transaction.restoProfile?.resto_name || transaction.resto?.store_name || storeName}
                                        </p>
                                        {#if transaction.restoProfile?.address}
                                            <p class="text-slate-500 dark:text-slate-400 text-xs line-clamp-2 mt-0.5">
                                                {transaction.restoProfile.address}
                                            </p>
                                        {/if}
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 pt-1">
                                        {#if restoMapLink}
                                            <a
                                                href={restoMapLink}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition flex items-center gap-1.5 shadow-sm"
                                            >
                                                <i class="ti ti-map-pin text-rose-500"></i>
                                                <span>Peta Lokasi</span>
                                            </a>
                                        {/if}

                                        {#if restoWhatsAppLink}
                                            <a
                                                href={restoWhatsAppLink}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="px-3 py-1.5 rounded-xl text-xs font-bold text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 hover:bg-emerald-100 transition flex items-center gap-1.5 shadow-sm"
                                            >
                                                <i class="ti ti-brand-whatsapp text-emerald-600"></i>
                                                <span>Hubungi Dapur</span>
                                            </a>
                                        {/if}
                                    </div>
                                </div>
                            </div>
                        </div>
                    {/if}

                    <!-- Shipping / Resi Info Card -->
                    {#if transaction.shipping_courier !== 'self_pickup' && transaction.shipping_courier !== 'digital'}
                        <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-6 shadow-2xs border border-slate-200/80 dark:border-slate-800 space-y-3">
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

                    <!-- ── STORE / RESTO RATING CARD ──────────────────── -->
                    {#if transaction.status === 'selesai'}
                        <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-6 shadow-2xs border border-slate-200/80 dark:border-slate-800 space-y-4">
                            {#if transaction.store_review}
                                <div class="flex items-start justify-between gap-3">
                                    <div class="space-y-1">
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200/80">
                                            <svg class="w-3.5 h-3.5 text-amber-500 fill-amber-500" viewBox="0 0 24 24">
                                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                            </svg>
                                            <span>Penilaian Anda</span>
                                        </div>
                                        <h3 class="text-base font-bold text-slate-900 dark:text-white pt-1">
                                            Ulasan untuk {transaction.resto_profile?.resto_name || transaction.resto?.store_name || storeName || 'Toko'}
                                        </h3>
                                    </div>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-2.5 py-1 rounded-full border border-emerald-200">
                                        <i class="ti ti-circle-check text-xs"></i>
                                        Terverifikasi
                                    </span>
                                </div>

                                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800 space-y-2">
                                    <div class="flex items-center gap-1">
                                        {#each [1, 2, 3, 4, 5] as star}
                                            <svg
                                                class="w-5 h-5 {transaction.store_review.rating >= star ? 'text-amber-400 fill-amber-400' : 'text-slate-300 dark:text-slate-700 fill-slate-100 dark:fill-slate-800'}"
                                                viewBox="0 0 24 24"
                                                fill="currentColor"
                                                stroke="currentColor"
                                                stroke-width="1"
                                            >
                                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                            </svg>
                                        {/each}
                                        <span class="ml-2 text-xs font-bold text-slate-700 dark:text-slate-300">
                                            {transaction.store_review.rating}.0 / 5.0
                                        </span>
                                    </div>
                                    {#if transaction.store_review.comment}
                                        <p class="text-xs text-slate-700 dark:text-slate-300 italic leading-relaxed pt-1">
                                            "{transaction.store_review.comment}"
                                        </p>
                                    {/if}
                                    <p class="text-[10px] text-slate-400 pt-1">
                                        Diberikan pada {formatDate(transaction.store_review.created_at)}
                                    </p>
                                </div>
                            {:else}
                                <div class="space-y-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <span class="text-xs font-bold text-amber-700 dark:text-amber-400 uppercase tracking-widest block">
                                                Pesanan Selesai
                                            </span>
                                            <h3 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">
                                                Beri Rating & Ulasan {isFoodOrder ? 'Resto' : 'Toko'}
                                            </h3>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                                Penilaian Anda akan langsung tampil secara real-time dan membantu pelanggan lain.
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Interactive Star Picker with Crisp SVG Stars -->
                                    <div class="py-2 flex flex-col sm:flex-row sm:items-center gap-3">
                                        <div class="flex items-center gap-1">
                                            {#each [1, 2, 3, 4, 5] as star}
                                                {@const isFilled = (hoverRating || selectedRating) >= star}
                                                <button
                                                    type="button"
                                                    onclick={() => (selectedRating = star)}
                                                    onmouseenter={() => (hoverRating = star)}
                                                    onmouseleave={() => (hoverRating = 0)}
                                                    class="p-1 rounded-xl transition-all duration-150 hover:scale-115 active:scale-95 cursor-pointer focus:outline-none focus:ring-2 focus:ring-amber-400/40"
                                                    aria-label={`Beri bintang ${star}`}
                                                >
                                                    <svg
                                                        class="w-8 h-8 sm:w-9 sm:h-9 transition-colors duration-150 {isFilled ? 'text-amber-400 fill-amber-400 drop-shadow-sm' : 'text-slate-300 dark:text-slate-600 fill-slate-100 dark:fill-slate-800'}"
                                                        viewBox="0 0 24 24"
                                                        stroke="currentColor"
                                                        stroke-width="1.2"
                                                    >
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                                    </svg>
                                                </button>
                                            {/each}
                                        </div>

                                        <!-- Rating Label Badge -->
                                        <div class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 px-3 py-1.5 rounded-full border border-amber-200/80 dark:border-amber-900/60 shadow-2xs">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                            <span class="font-extrabold">{hoverRating || selectedRating} / 5</span>
                                            <span class="text-amber-400 dark:text-amber-600">•</span>
                                            <span>
                                                {#if (hoverRating || selectedRating) === 5}
                                                    Luar Biasa Puas!
                                                {:else if (hoverRating || selectedRating) === 4}
                                                    Enak & Puas
                                                {:else if (hoverRating || selectedRating) === 3}
                                                    Cukup Baik
                                                {:else if (hoverRating || selectedRating) === 2}
                                                    Kurang Puas
                                                {:else}
                                                    Sangat Kurang
                                                {/if}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Comment Textarea -->
                                    <div class="space-y-1.5">
                                        <label for="rating-comment-input" class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                            Catatan / Ulasan Anda (Opsional)
                                        </label>
                                        <textarea
                                            id="rating-comment-input"
                                            bind:value={reviewComment}
                                            rows="3"
                                            placeholder="Ceritakan kepuasan rasa makanan, kebersihan kemasan, atau kecepatan pelayanan..."
                                            class="w-full text-xs rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-500 transition"
                                        ></textarea>
                                    </div>

                                    <!-- Submit Button -->
                                    <div class="flex justify-end pt-1">
                                        <button
                                            type="button"
                                            onclick={submitStoreReview}
                                            disabled={submittingReview}
                                            class="px-5 py-2.5 rounded-xl text-xs font-bold text-white shadow-xs transition hover:opacity-95 active:scale-95 disabled:opacity-50 cursor-pointer flex items-center gap-1.5"
                                            style="background-color: {primary};"
                                        >
                                            {#if submittingReview}
                                                <i class="ti ti-loader-2 animate-spin text-sm"></i>
                                                <span>Mengirim Ulasan...</span>
                                            {:else}
                                                <i class="ti ti-send text-sm"></i>
                                                <span>Kirim Penilaian {isFoodOrder ? 'Resto' : 'Toko'}</span>
                                            {/if}
                                        </button>
                                    </div>
                                </div>
                            {/if}
                        </div>
                    {/if}

                    </div>
                    <!-- ════════════════════════════════════════════════════════
                         END LEFT COLUMN
                         ════════════════════════════════════════════════════════ -->

                    <!-- ════════════════════════════════════════════════════════
                         RIGHT COLUMN (5 cols): Payment, Order Items, Actions
                         ════════════════════════════════════════════════════════ -->
                    <div class="lg:col-span-5 space-y-6 lg:sticky lg:top-6">

                        <!-- Payment Information Card -->
                        <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-6 shadow-2xs border border-slate-200/80 dark:border-slate-800 space-y-4">
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
                                        class="px-3 py-1.5 rounded-xl text-xs font-bold bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 hover:bg-slate-100 transition shadow-xs cursor-pointer"
                                    >
                                        <i class="ti ti-copy mr-1"></i> Salin Jumlah
                                    </button>
                                </div>

                                <!-- Payment Status Alerts -->
                                {#if isCodMethod}
                                    {#if transaction.status === 'selesai' || transaction.payment_status === 'paid'}
                                        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 space-y-1.5 text-xs text-emerald-900 dark:text-emerald-200">
                                            <p class="font-bold flex items-center gap-1.5 text-sm text-emerald-700 dark:text-emerald-300">
                                                <i class="ti ti-circle-check text-base"></i>
                                                Pembayaran Tunai Telah Lunas
                                            </p>
                                            <p class="text-[11px] text-emerald-800 dark:text-emerald-300">
                                                Uang tunai sebesar <span class="font-bold">{formatRupiah(transaction.grand_total)}</span> telah diterima dengan baik oleh {transaction.shipping_courier === 'self_pickup' ? 'kasir resto' : 'kurir resto'}.
                                            </p>
                                        </div>
                                    {:else}
                                        <div class="p-4 rounded-2xl bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 space-y-2 text-xs text-amber-900 dark:text-amber-200">
                                            <p class="font-bold flex items-center gap-1.5 text-sm text-amber-800 dark:text-amber-300">
                                                <i class="ti ti-cash text-base"></i>
                                                {transaction.shipping_courier === 'self_pickup' ? 'Bayar Tunai di Kasir Resto' : 'Bayar Tunai ke Kurir Resto (COD)'}
                                            </p>
                                            <p class="text-[11px] text-amber-800/90 dark:text-amber-300/90 leading-relaxed">
                                                {#if transaction.shipping_courier === 'self_pickup'}
                                                    Silakan siapkan uang tunai pas sebesar <span class="font-bold">{formatRupiah(transaction.grand_total)}</span> untuk dibayarkan langsung di kasir resto saat mengambil pesanan makanan Anda.
                                                {:else}
                                                    Silakan siapkan uang tunai pas sebesar <span class="font-bold">{formatRupiah(transaction.grand_total)}</span> untuk diserahkan langsung kepada kurir resto saat pesanan makanan tiba di alamat Anda.
                                                {/if}
                                            </p>
                                            <div class="flex items-center gap-2 pt-1 text-[11px] text-amber-700 dark:text-amber-400 font-medium">
                                                <i class="ti ti-info-circle"></i>
                                                <span>Tidak perlu transfer bank. Pembayaran diselesaikan saat serah terima makanan.</span>
                                            </div>
                                        </div>
                                    {/if}
                                {:else if isQris && qrisData && transaction.status === 'belum_bayar'}
                                    <!-- Dynamic QRIS Instruction Block -->
                                    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-rose-200 dark:border-rose-900/60 flex flex-col items-center justify-center space-y-4 shadow-sm">
                                        <div class="text-center">
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400 mb-1">
                                                <i class="ti ti-qrcode"></i> QRIS Dinamis Otomatis
                                            </span>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                Scan QRIS di bawah ini menggunakan GoPay, OVO, Dana, ShopeePay, BCA Mobile, atau m-Banking apapun.
                                            </p>
                                            {#if qrisData.nmid}
                                                <p class="text-[10px] text-slate-400 mt-0.5">NMID: {qrisData.nmid}</p>
                                            {/if}
                                        </div>

                                        <div class="p-2.5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-inner flex items-center justify-center">
                                            {#if qrisData.image}
                                                <img
                                                    src={qrisData.image}
                                                    alt="QRIS Dinamis"
                                                    class="w-52 h-52 object-contain rounded-xl"
                                                    onerror={(e) => {
                                                        if (qrisData.string) {
                                                            const fallback = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrisData.string)}`;
                                                            if (e.currentTarget.src !== fallback) {
                                                                e.currentTarget.src = fallback;
                                                            }
                                                        }
                                                    }}
                                                />
                                            {:else if qrisData.string}
                                                <img
                                                    src={`https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrisData.string)}`}
                                                    alt="QRIS Dinamis"
                                                    class="w-52 h-52 object-contain rounded-xl"
                                                />
                                            {:else if qrisData.error}
                                                <div class="w-64 min-h-52 p-4 flex flex-col items-center justify-center bg-rose-50/70 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900 rounded-2xl text-center space-y-2">
                                                    <i class="ti ti-alert-circle text-3xl text-rose-500"></i>
                                                    <p class="text-xs font-bold text-rose-700 dark:text-rose-300">
                                                        QRIS Belum Tersedia
                                                    </p>
                                                    <p class="text-[11px] text-rose-600/90 dark:text-rose-400 leading-snug">
                                                        {qrisData.error}
                                                    </p>
                                                    <p class="text-[10px] text-slate-400">
                                                        Silakan daftarkan QRIS di dashboard gateway, lalu klik tombol muat ulang di bawah.
                                                    </p>
                                                </div>
                                            {:else}
                                                <div class="w-52 h-52 flex items-center justify-center bg-slate-100 dark:bg-slate-800 rounded-xl">
                                                    <i class="ti ti-qrcode text-4xl text-slate-400"></i>
                                                </div>
                                            {/if}
                                        </div>

                                        <div class="flex flex-col sm:flex-row items-center gap-2 w-full pt-1">
                                            {#if qrisData.image}
                                                <button
                                                    type="button"
                                                    onclick={downloadQrisImage}
                                                    class="w-full sm:flex-1 px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 transition flex items-center justify-center gap-1.5 shadow-sm"
                                                >
                                                    <i class="ti ti-download"></i> Unduh QR Code
                                                </button>
                                            {/if}
                                            <button
                                                type="button"
                                                onclick={checkPaymentStatus}
                                                disabled={checkingStatus}
                                                class="w-full sm:flex-1 px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition flex items-center justify-center gap-1.5 border border-slate-200 dark:border-slate-700 shadow-sm"
                                            >
                                                <i class="ti ti-refresh text-sm {checkingStatus ? 'animate-spin' : ''}"></i>
                                                <span>{checkingStatus ? 'Mengecek...' : (qrisData?.error && !qrisData?.image ? 'Muat Ulang QRIS' : 'Cek Status Pembayaran')}</span>
                                            </button>
                                        </div>

                                        <div class="flex items-center justify-center gap-1.5 text-[11px] text-slate-400">
                                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                            <span>Mengecek status pembayaran otomatis secara berkala...</span>
                                        </div>

                                        <div class="w-full text-left text-xs text-slate-500 bg-slate-50 dark:bg-slate-800/50 p-3 rounded-xl space-y-1">
                                            <p class="font-bold text-slate-700 dark:text-slate-300">Cara Pembayaran:</p>
                                            <ol class="list-decimal pl-4 space-y-0.5 text-[11px]">
                                                <li>Buka aplikasi m-Banking atau E-Wallet pilihan Anda.</li>
                                                <li>Pilih menu <strong>Scan / Bayar QRIS</strong>.</li>
                                                <li>Arahkan kamera ke QR Code di atas.</li>
                                                <li>Periksa nominal tagihan ({formatRupiah(transaction.grand_total)}) dan konfirmasi pembayaran.</li>
                                            </ol>
                                        </div>
                                    </div>
                                {:else if transaction.payment_method?.type === 'manual' && (transaction.status === 'belum_bayar' || transaction.status === 'menunggu')}
                                    <!-- Manual Bank Transfer Instruction -->
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
                                                class="w-full py-3 px-4 rounded-2xl font-bold text-sm text-white shadow transition flex items-center justify-center gap-2 cursor-pointer"
                                                style="background: {secondary};"
                                            >
                                                <i class="ti ti-upload text-base"></i>
                                                Upload Bukti Transfer Pembayaran
                                            </button>
                                        {/if}
                                    </div>
                                {:else}
                                    <!-- General Paid State -->
                                    <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 space-y-1.5 text-xs text-emerald-900 dark:text-emerald-200">
                                        <p class="font-bold flex items-center gap-1.5 text-sm text-emerald-700 dark:text-emerald-300">
                                            <i class="ti ti-circle-check text-base"></i>
                                            Pembayaran Telah Lunas
                                        </p>
                                        <p class="text-[11px] text-emerald-800 dark:text-emerald-300">
                                            Total <span class="font-bold">{formatRupiah(transaction.grand_total)}</span> telah berhasil diselesaikan melalui {transaction.payment_method?.name || 'Metode Pembayaran'}.
                                        </p>
                                    </div>
                                {/if}
                            </div>
                        </div>

                        <!-- Items List Card -->
                        <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-6 shadow-2xs border border-slate-200/80 dark:border-slate-800 space-y-4">
                            <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2 text-base border-b border-slate-100 dark:border-slate-800 pb-3">
                                <i class="ti ti-package text-xl" style="color: {primary};"></i>
                                Rincian Produk Dipesan ({transaction.items?.length || 0})
                            </h3>

                            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                                {#each transaction.items || [] as item}
                                    {@const unitPrice = Number(item.price) > 0 ? Number(item.price) : (Number(item.subtotal) > 0 && item.quantity > 0 ? Number(item.subtotal) / item.quantity : 0)}
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
                                            <OrderItemModifiers {item} class="mt-1" />
                                            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 mt-1.5">
                                                <span>{item.quantity} x {formatRupiah(unitPrice)}</span>
                                                <span class="font-bold text-slate-900 dark:text-white">
                                                    {formatRupiah(item.subtotal || unitPrice * item.quantity)}
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
                        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                            <Link
                                href="/"
                                class="px-5 py-3 rounded-2xl text-xs font-bold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:bg-slate-50 transition flex items-center gap-2 shadow-2xs"
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
                                        class="px-5 py-3 rounded-2xl text-xs font-bold text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 hover:bg-emerald-100 transition flex items-center gap-2 shadow-2xs"
                                    >
                                        <i class="ti ti-brand-whatsapp text-base"></i>
                                        Hubungi Penjual
                                    </a>
                                {/if}
                            </div>
                        </div>

                    </div>
                    <!-- ════════════════════════════════════════════════════════
                         END RIGHT COLUMN
                         ════════════════════════════════════════════════════════ -->

                </div>
                <!-- ════════════════════════════════════════════════════════
                     END RESPONSIVE 2-COLUMN GRID
                     ════════════════════════════════════════════════════════ -->
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
